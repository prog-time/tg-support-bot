<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Avito\DTOs\AvitoUpdateDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendAvitoTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\TestCase;

/**
 * Covers App\Modules\Telegram\Jobs\SendAvitoTelegramMessageJob::handle().
 *
 * Mirrors tests/Feature/Jobs/SendMaxTelegramMessageJobTest.php, adapted for
 * Avito's fields (from_id = author_id, to_id = Telegram message_id).
 */
class SendAvitoTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private AvitoUpdateDto $dto;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $this->dto = new AvitoUpdateDto(
            event_id: 'evt-1',
            type: 'message',
            chatId: 'u2i-job-test',
            author_id: 555,
            message_id: 'avito-msg-1',
            text: 'Тестовое сообщение',
            rawData: [],
        );

        $this->botUser = BotUser::getUserByChatId($this->dto->chatId, 'avito');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_successful_send_creates_incoming_message_with_avito_fields(): void
    {
        $this->botUser->topic_id = 321;
        $this->botUser->save();

        $telegramMessageId = 9988;
        $dtoParams = TelegramAnswerDtoMock::getDtoParams();
        $dtoParams['result']['message_id'] = $telegramMessageId;
        $dtoParams['result']['text'] = $this->dto->text;
        $answerDto = TelegramAnswerDtoMock::getDto($dtoParams);

        /** @var TelegramMethods&Mockery\MockInterface $mockTelegramMethods */
        $mockTelegramMethods = Mockery::mock(TelegramMethods::class);
        $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($answerDto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'message_thread_id' => $this->botUser->topic_id,
            'text' => $this->dto->text,
        ]);

        $job = new SendAvitoTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $queryParams,
            $mockTelegramMethods,
        );
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'avito',
            'message_type' => 'incoming',
            'from_id' => 555,
            'to_id' => $telegramMessageId,
            'text' => 'Тестовое сообщение',
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $this->botUser->id)->count());
    }

    public function test_dispatches_topic_create_job_chain_when_bot_user_has_no_topic(): void
    {
        Queue::fake();

        // Freshly created BotUser has no topic_id.
        $this->assertNull($this->botUser->topic_id);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'text' => $this->dto->text,
        ]);

        $job = new SendAvitoTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $queryParams,
        );
        $job->handle();

        Queue::assertPushed(TopicCreateJob::class);

        // No message can be persisted yet — topic creation must happen first.
        $this->assertSame(0, Message::where('bot_user_id', $this->botUser->id)->count());
    }

    public function test_missing_bot_user_does_not_throw_and_logs_warning(): void
    {
        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'BotUser not found for Avito forward', Mockery::type('array'));

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'text' => $this->dto->text,
        ]);

        $missingBotUserId = $this->botUser->id + 999999;

        $job = new SendAvitoTelegramMessageJob(
            $missingBotUserId,
            $this->dto,
            $queryParams,
        );

        // Must not throw — the failure is swallowed and logged.
        $job->handle();

        $this->assertSame(0, Message::where('bot_user_id', $missingBotUserId)->count());
    }
}
