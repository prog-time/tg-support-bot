<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\TestCase;

/**
 * Covers App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob::handle().
 *
 * Mirrors tests/Unit/Modules/Telegram/Jobs/SendAvitoTelegramMessageJobTest.php,
 * adapted for Email's fields (no numeric sender id, so from_id is always 0).
 */
class SendEmailTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private EmailUpdateDto $dto;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $this->dto = new EmailUpdateDto(
            chatId: 'job-test@example.com',
            senderName: 'Job Test',
            subject: 'Subject',
            text: 'Тестовое сообщение',
            messageId: 'email-msg-1@example.com',
            references: null,
        );

        $this->botUser = BotUser::getUserByChatId($this->dto->chatId, 'email');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_successful_send_creates_incoming_message_with_email_fields(): void
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

        $job = new SendEmailTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $queryParams,
            $mockTelegramMethods,
        );
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'email',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => $telegramMessageId,
            'text' => 'Тестовое сообщение',
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $this->botUser->id)->count());
    }

    public function test_dispatches_topic_create_job_chain_when_bot_user_has_no_topic(): void
    {
        Queue::fake();

        $this->assertNull($this->botUser->topic_id);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'text' => $this->dto->text,
        ]);

        $job = new SendEmailTelegramMessageJob(
            $this->botUser->id,
            $this->dto,
            $queryParams,
        );
        $job->handle();

        Queue::assertPushed(TopicCreateJob::class);

        $this->assertSame(0, Message::where('bot_user_id', $this->botUser->id)->count());
    }

    public function test_missing_bot_user_does_not_throw_and_logs_warning(): void
    {
        Log::shouldReceive('channel')->with('app')->andReturnSelf();
        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'BotUser not found for Email forward', Mockery::type('array'));

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'text' => $this->dto->text,
        ]);

        $missingBotUserId = $this->botUser->id + 999999;

        $job = new SendEmailTelegramMessageJob(
            $missingBotUserId,
            $this->dto,
            $queryParams,
        );

        $job->handle();

        $this->assertSame(0, Message::where('bot_user_id', $missingBotUserId)->count());
    }
}
