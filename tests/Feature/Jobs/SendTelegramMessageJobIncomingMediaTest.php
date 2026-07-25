<?php

namespace Tests\Feature\Jobs;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

/**
 * Hermetic tests for SendTelegramMessageJob::saveMessage() text/caption handling.
 *
 * Unlike SendTelegramMessageJobTest, this class does not run TopicCreateJob in
 * setUp (no real HTTP) — the BotUser is created with a topic_id directly and
 * TelegramMethods is mocked, so the incoming-message branch reaches saveMessage.
 */
class SendTelegramMessageJobIncomingMediaTest extends TestCase
{
    use RefreshDatabase;

    private function makeBotUser(): BotUser
    {
        return BotUser::create([
            'chat_id' => 700700,
            'platform' => 'telegram',
            'topic_id' => 4242,
        ]);
    }

    /**
     * @param array<string, mixed> $messageOverrides
     */
    private function incomingDtoWith(array $messageOverrides, BotUser $botUser): \App\Modules\Telegram\DTOs\TelegramUpdateDto
    {
        return TelegramUpdateDtoMock::getDto([
            'update_id' => time(),
            'message' => array_merge([
                'message_id' => 555,
                'from' => [
                    'id' => 1,
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'username' => 'usertest',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => $botUser->chat_id,
                    'type' => 'private',
                ],
                'date' => time(),
            ], $messageOverrides),
        ]);
    }

    private function mockTelegram(): TelegramMethods
    {
        /** @var TelegramMethods&\Mockery\MockInterface $mock */
        $mock = \Mockery::mock(TelegramMethods::class);
        $mock->shouldReceive('sendQueryTelegram')->andReturn(TelegramAnswerDtoMock::getDto());

        return $mock;
    }

    public function test_incoming_photo_saves_caption_as_text_with_attachment(): void
    {
        $botUser = $this->makeBotUser();

        $dto = $this->incomingDtoWith([
            'photo' => [['file_id' => 'PHOTO_FILE_ID']],
            'caption' => 'Подпись к фото',
        ], $botUser);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendPhoto',
            'chat_id' => '-100123456',
            'photo' => 'PHOTO_FILE_ID',
            'caption' => 'Подпись к фото',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'incoming', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => 'Подпись к фото',
        ]);

        $message = Message::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($message);
        $this->assertDatabaseHas('message_attachments', [
            'message_id' => $message->id,
            'file_type' => 'photo',
            'file_id' => 'PHOTO_FILE_ID',
        ]);
    }

    public function test_incoming_photo_without_caption_saves_null_text(): void
    {
        $botUser = $this->makeBotUser();

        $dto = $this->incomingDtoWith([
            'photo' => [['file_id' => 'PHOTO_NO_CAPTION']],
        ], $botUser);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendPhoto',
            'chat_id' => '-100123456',
            'photo' => 'PHOTO_NO_CAPTION',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'incoming', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => null,
        ]);
    }

    /**
     * issue #46 — when Telegram gives no text/caption at all (e.g. video,
     * poll, GIF), the unsupported-type placeholder built into
     * queryParams->text by TgMessageService::sendUnsupportedTypeNotice()
     * must still end up in messages.text — it's the only "real" content
     * we have to show the manager.
     */
    public function test_incoming_unsupported_type_falls_back_to_query_params_text(): void
    {
        $botUser = $this->makeBotUser();

        // No 'text', no 'caption' — mirrors a video/poll/etc. update.
        $dto = $this->incomingDtoWith([], $botUser);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => '-100123456',
            'text' => '⚠️ Клиент отправил сообщение неподдерживаемого типа (видео). Попросите переслать текстом, фото, документом или голосовым.',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'incoming', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => '⚠️ Клиент отправил сообщение неподдерживаемого типа (видео). Попросите переслать текстом, фото, документом или голосовым.',
        ]);
    }

    public function test_incoming_plain_text_still_saved(): void
    {
        $botUser = $this->makeBotUser();

        $dto = $this->incomingDtoWith([
            'text' => 'Просто текст',
        ], $botUser);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => '-100123456',
            'text' => 'Просто текст',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'incoming', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => 'Просто текст',
        ]);
    }
}
