<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
            'text' => "Тема: Subject\n\nТестовое сообщение",
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $this->botUser->id)->count());
    }

    public function test_persists_the_readable_display_text_not_the_html_escaped_wire_text(): void
    {
        // EmailMessageService HTML-escapes '<'/'>'/'&' in queryParams->text
        // before sending (parse_mode=html — a mail client's own reply-quote
        // header routinely contains a bare "<address" that would otherwise
        // break Telegram's parser). saveMessage() must NOT persist that
        // escaped string — it must persist $updateDto->displayText() (subject
        // + body, unescaped), or the admin workspace's Blade view would
        // re-escape it again and show a literal "&lt;" to the manager.
        $this->botUser->topic_id = 555;
        $this->botUser->save();

        $dtoWithAngleBracket = new EmailUpdateDto(
            chatId: $this->dto->chatId,
            senderName: $this->dto->senderName,
            subject: 'Subject',
            text: 'Re: see <support@example.com> for details',
            messageId: $this->dto->messageId,
            references: null,
        );

        $telegramMessageId = 1234;
        $dtoParams = TelegramAnswerDtoMock::getDtoParams();
        $dtoParams['result']['message_id'] = $telegramMessageId;
        $answerDto = TelegramAnswerDtoMock::getDto($dtoParams);

        /** @var TelegramMethods&Mockery\MockInterface $mockTelegramMethods */
        $mockTelegramMethods = Mockery::mock(TelegramMethods::class);
        $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($answerDto);

        $escapedWireText = 'Тема: Subject' . "\n\n" . 'Re: see &lt;support@example.com&gt; for details';
        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $this->defaultGroupId,
            'message_thread_id' => $this->botUser->topic_id,
            'text' => $escapedWireText,
        ]);

        $job = new SendEmailTelegramMessageJob(
            $this->botUser->id,
            $dtoWithAngleBracket,
            $queryParams,
            $mockTelegramMethods,
        );
        $job->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'text' => $dtoWithAngleBracket->displayText(),
        ]);
        $this->assertDatabaseMissing('messages', [
            'bot_user_id' => $this->botUser->id,
            'text' => $escapedWireText,
        ]);
    }

    public function test_records_message_attachment_when_update_dto_carries_one(): void
    {
        Storage::fake('local');

        $this->botUser->topic_id = 777;
        $this->botUser->save();

        $storedPath = 'chat-attachments/incoming-photo.jpg';
        Storage::disk('local')->put($storedPath, 'fake-bytes');

        $dtoWithAttachment = new EmailUpdateDto(
            chatId: $this->dto->chatId,
            senderName: $this->dto->senderName,
            subject: $this->dto->subject,
            text: $this->dto->text,
            messageId: $this->dto->messageId,
            references: null,
            attachments: [[
                'path' => '/tmp/does-not-matter-already-consumed-by-telegram-upload.jpg',
                'storedPath' => $storedPath,
                'name' => 'photo.jpg',
                'mime' => 'image/jpeg',
            ]],
        );

        $answerDto = TelegramAnswerDtoMock::getDto(TelegramAnswerDtoMock::getDtoParams());

        /** @var TelegramMethods&Mockery\MockInterface $mockTelegramMethods */
        $mockTelegramMethods = Mockery::mock(TelegramMethods::class);
        $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($answerDto);

        $queryParams = TGTextMessageDto::from([
            'methodQuery' => 'sendPhoto',
            'chat_id' => $this->defaultGroupId,
            'message_thread_id' => $this->botUser->topic_id,
            'uploaded_file_path' => $dtoWithAttachment->attachments[0]['path'],
        ]);

        $job = new SendEmailTelegramMessageJob(
            $this->botUser->id,
            $dtoWithAttachment,
            $queryParams,
            $mockTelegramMethods,
        );
        $job->handle();

        $message = Message::where('bot_user_id', $this->botUser->id)->first();
        $this->assertNotNull($message);

        $attachment = MessageAttachment::where('message_id', $message->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame($storedPath, $attachment->file_id);
        $this->assertSame('photo', $attachment->file_type);
        $this->assertSame('photo.jpg', $attachment->file_name);
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
