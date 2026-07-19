<?php

namespace Tests\Unit\Modules\Avito\Services;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Avito\DTOs\AvitoUpdateDto;
use App\Modules\Avito\Services\AvitoMessageService;
use App\Modules\Telegram\Jobs\SendAvitoTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers App\Modules\Avito\Services\AvitoMessageService::handleUpdate().
 *
 * Two mutually exclusive branches (mirrors MaxMessageServiceTest / the
 * group-OFF coverage in IncomingMessagePersistenceTest):
 *   - group-ON  → forwards via SendAvitoTelegramMessageJob, no direct Message row
 *   - group-OFF → persists a Message row directly, then maybeDispatchAi()
 *
 * TestCase::setUp() seeds a default telegram.group_id, so group-ON is the
 * baseline state; group-OFF tests must call clearGroupId() first.
 */
class AvitoMessageServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function clearGroupId(): void
    {
        app(\App\Services\Settings\SettingsService::class)->forget('telegram.group_id');
    }

    private function makeDto(
        string $chatId = 'u2i-test-chat',
        int|string $authorId = 777,
        ?string $text = 'Здравствуйте',
        string $type = 'message',
    ): AvitoUpdateDto {
        return new AvitoUpdateDto(
            event_id: 'evt-1',
            type: $type,
            chatId: $chatId,
            author_id: $authorId,
            message_id: 'msg-1',
            text: $text,
            rawData: [],
        );
    }

    // ── group-ON ─────────────────────────────────────────────────────────────

    public function test_group_on_dispatches_avito_telegram_job(): void
    {
        $dto = $this->makeDto();
        $botUser = BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAvitoTelegramMessageJob::class, function (SendAvitoTelegramMessageJob $job) use ($botUser, $dto): bool {
            return $job->botUserId === $botUser->id
                && $job->updateDto === $dto
                && $job->queryParams->methodQuery === 'sendMessage'
                && $job->queryParams->text === 'Здравствуйте'
                && $job->queryParams->message_thread_id === $botUser->topic_id;
        });
    }

    public function test_group_on_does_not_persist_message_directly(): void
    {
        $dto = $this->makeDto();
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        // Jobs are faked, so the job never runs — the direct-persist path must
        // not have created a row either. Exactly zero rows at this point.
        $this->assertSame(0, Message::where('platform', 'avito')->count());
    }

    // ── group-OFF ────────────────────────────────────────────────────────────

    public function test_group_off_persists_incoming_message(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'u2i-group-off', authorId: 999, text: 'Привет');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'avito',
            'message_type' => 'incoming',
            'from_id' => 999,
            'to_id' => 0,
            'text' => 'Привет',
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_group_off_does_not_dispatch_avito_telegram_job(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'u2i-group-off-2');
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAvitoTelegramMessageJob::class);
    }

    // ── Ignored inputs ───────────────────────────────────────────────────────

    public function test_unknown_event_type_does_nothing(): void
    {
        $dto = $this->makeDto(chatId: 'u2i-unknown-type', type: 'reaction');
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAvitoTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'avito')->count());
    }

    public function test_empty_text_does_nothing(): void
    {
        $dto = $this->makeDto(chatId: 'u2i-empty-text', text: '');
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAvitoTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'avito')->count());
    }

    public function test_null_text_does_nothing(): void
    {
        $dto = $this->makeDto(chatId: 'u2i-null-text', text: null);
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAvitoTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'avito')->count());
    }

    // ── No double-write invariant ───────────────────────────────────────────

    public function test_never_creates_two_message_rows_regardless_of_branch(): void
    {
        // group-ON: the job persists the row (mirrored by SendAvitoTelegramMessageJobTest),
        // so under Queue::fake() (job never runs) the service itself must create 0 rows.
        $dtoOn = $this->makeDto(chatId: 'u2i-invariant-on');
        $botUserOn = BotUser::getUserByChatId($dtoOn->chatId, 'avito');
        (new AvitoMessageService($dtoOn))->handleUpdate();

        $this->assertSame(0, Message::where('bot_user_id', $botUserOn->id)->count());

        // group-OFF: the service persists directly — exactly 1 row, never 2.
        $this->clearGroupId();
        $dtoOff = $this->makeDto(chatId: 'u2i-invariant-off');
        $botUserOff = BotUser::getUserByChatId($dtoOff->chatId, 'avito');
        (new AvitoMessageService($dtoOff))->handleUpdate();

        $this->assertSame(1, Message::where('bot_user_id', $botUserOff->id)->count());
    }

    // ── AI dispatch ──────────────────────────────────────────────────────────

    public function test_group_off_dispatches_ai_reply_job_when_auto_reply_enabled(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', true);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'u2i-ai-auto', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAiReplyJob::class, function (SendAiReplyJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->updateDto === null
                && $job->userMessage === 'Нужна помощь';
        });
        Queue::assertNotPushed(SendAiDraftJob::class);
    }

    public function test_group_off_dispatches_ai_draft_job_when_auto_reply_disabled(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', false);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'u2i-ai-draft', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAiDraftJob::class, function (SendAiDraftJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->updateDto === null
                && $job->userMessage === 'Нужна помощь';
        });
        Queue::assertNotPushed(SendAiReplyJob::class);
    }

    public function test_ai_not_dispatched_when_should_ai_reply_denies(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', true);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(false);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'u2i-ai-denied');
        BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAiReplyJob::class);
        Queue::assertNotPushed(SendAiDraftJob::class);
    }

    public function test_group_on_also_considers_ai_dispatch(): void
    {
        // sendMessage() (group-ON) calls maybeDispatchAi() too, after
        // dispatching the forward job.
        $this->seedSetting('ai.auto_reply', false);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'u2i-ai-group-on', text: 'Здравствуйте, есть вопрос');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'avito');

        (new AvitoMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAvitoTelegramMessageJob::class);
        Queue::assertPushed(SendAiDraftJob::class, function (SendAiDraftJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id;
        });
    }
}
