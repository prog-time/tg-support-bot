<?php

namespace Tests\Unit\Modules\Email\Services;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Email\Services\EmailMessageService;
use App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers App\Modules\Email\Services\EmailMessageService::handleUpdate().
 *
 * Two mutually exclusive branches (mirrors AvitoMessageServiceTest):
 *   - group-ON  → forwards via SendEmailTelegramMessageJob, no direct Message row
 *   - group-OFF → persists a Message row directly, then maybeDispatchAi()
 *
 * TestCase::setUp() seeds a default telegram.group_id, so group-ON is the
 * baseline state; group-OFF tests must call clearGroupId() first.
 */
class EmailMessageServiceTest extends TestCase
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
        string $chatId = 'user@example.com',
        string $senderName = 'User Name',
        ?string $text = 'Здравствуйте',
        string $subject = 'Нужна помощь',
    ): EmailUpdateDto {
        return new EmailUpdateDto(
            chatId: $chatId,
            senderName: $senderName,
            subject: $subject,
            text: $text,
            messageId: 'msg-1@example.com',
            references: null,
        );
    }

    // ── group-ON ─────────────────────────────────────────────────────────────

    public function test_group_on_dispatches_email_telegram_job(): void
    {
        $dto = $this->makeDto();
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job) use ($botUser, $dto): bool {
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
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    public function test_group_on_is_handled_successfully(): void
    {
        $dto = $this->makeDto();
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
    }

    // ── group-OFF ────────────────────────────────────────────────────────────

    public function test_group_off_persists_incoming_message(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'group-off@example.com', text: 'Привет');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'email',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => 'Привет',
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_group_off_does_not_dispatch_email_telegram_job(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'group-off-2@example.com');
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
    }

    // ── Ignored inputs ───────────────────────────────────────────────────────

    public function test_empty_text_does_nothing_but_is_still_handled(): void
    {
        $dto = $this->makeDto(chatId: 'empty-text@example.com', text: '');
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    public function test_null_text_does_nothing_but_is_still_handled(): void
    {
        $dto = $this->makeDto(chatId: 'null-text@example.com', text: null);
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    // ── captureProfile ───────────────────────────────────────────────────────

    public function test_captures_display_name_when_empty(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'name-capture@example.com', senderName: 'Ivan Petrov');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');
        $this->assertNull($botUser->display_name);

        (new EmailMessageService($dto))->handleUpdate();

        $botUser->refresh();
        $this->assertSame('Ivan Petrov', $botUser->display_name);
    }

    public function test_does_not_overwrite_existing_display_name(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'name-keep@example.com', senderName: 'Mail Client Name');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');
        $botUser->update(['display_name' => 'Operator Set Name']);

        (new EmailMessageService($dto))->handleUpdate();

        $botUser->refresh();
        $this->assertSame('Operator Set Name', $botUser->display_name);
    }

    // ── No double-write invariant ───────────────────────────────────────────

    public function test_never_creates_two_message_rows_regardless_of_branch(): void
    {
        $dtoOn = $this->makeDto(chatId: 'invariant-on@example.com');
        $botUserOn = BotUser::getUserByChatId($dtoOn->chatId, 'email');
        (new EmailMessageService($dtoOn))->handleUpdate();

        $this->assertSame(0, Message::where('bot_user_id', $botUserOn->id)->count());

        $this->clearGroupId();
        $dtoOff = $this->makeDto(chatId: 'invariant-off@example.com');
        $botUserOff = BotUser::getUserByChatId($dtoOff->chatId, 'email');
        (new EmailMessageService($dtoOff))->handleUpdate();

        $this->assertSame(1, Message::where('bot_user_id', $botUserOff->id)->count());
    }

    // ── Thread store ─────────────────────────────────────────────────────────

    public function test_remembers_thread_for_reply_headers(): void
    {
        $this->clearGroupId();
        Cache::flush();

        $dto = $this->makeDto(chatId: 'thread@example.com', subject: 'Order #42');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $thread = app(\App\Modules\Email\Services\EmailThreadStore::class)->get($botUser->id);
        $this->assertNotNull($thread);
        $this->assertSame('msg-1@example.com', $thread['message_id']);
        $this->assertSame('Order #42', $thread['subject']);
    }

    // ── AI dispatch ──────────────────────────────────────────────────────────

    public function test_group_off_dispatches_ai_reply_job_when_auto_reply_enabled(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', true);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'ai-auto@example.com', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

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

        $dto = $this->makeDto(chatId: 'ai-draft@example.com', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

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

        $dto = $this->makeDto(chatId: 'ai-denied@example.com');
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAiReplyJob::class);
        Queue::assertNotPushed(SendAiDraftJob::class);
    }
}
