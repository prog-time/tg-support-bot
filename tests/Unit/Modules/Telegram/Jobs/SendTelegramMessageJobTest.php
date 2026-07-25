<?php

namespace Tests\Unit\Modules\Telegram\Jobs;

use App\Models\BotUser;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

/**
 * issue #46 — SendTelegramMessageJob::saveMessage() must fall back to
 * queryParams->text for incoming messages when Telegram gave no text/caption
 * at all (video, poll, GIF, dice, venue, game): that's where
 * TgMessageService::sendUnsupportedTypeNotice() puts its placeholder, and
 * without the fallback the notice would reach the topic but never land in
 * the messages table.
 */
class SendTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeBotUser(): BotUser
    {
        return BotUser::create([
            'chat_id' => 800800,
            'platform' => 'telegram',
            'topic_id' => 5252,
        ]);
    }

    private function mockTelegram(): TelegramMethods
    {
        /** @var TelegramMethods&\Mockery\MockInterface $mock */
        $mock = \Mockery::mock(TelegramMethods::class);
        $mock->shouldReceive('sendQueryTelegram')->andReturn(TelegramAnswerDtoMock::getDto());

        return $mock;
    }

    public function test_incoming_message_prefers_update_text_over_query_params_text(): void
    {
        $botUser = $this->makeBotUser();

        $dto = TelegramUpdateDtoMock::getDto([
            'update_id' => time(),
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Test'],
                'chat' => ['id' => $botUser->chat_id, 'type' => 'private'],
                'date' => time(),
                'text' => 'исходный текст клиента',
            ],
        ]);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => '-100123456',
            'text' => 'исходный текст клиента',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'incoming', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'incoming',
            'text' => 'исходный текст клиента',
        ]);
    }

    public function test_incoming_message_with_no_update_text_or_caption_falls_back_to_query_params_text(): void
    {
        $botUser = $this->makeBotUser();

        $dto = TelegramUpdateDtoMock::getDto([
            'update_id' => time(),
            'message' => [
                'message_id' => 2,
                'from' => ['id' => 1, 'is_bot' => false, 'first_name' => 'Test'],
                'chat' => ['id' => $botUser->chat_id, 'type' => 'private'],
                'date' => time(),
            ],
        ]);

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

    public function test_outgoing_message_saves_query_params_text(): void
    {
        $botUser = $this->makeBotUser();

        $dto = TelegramUpdateDtoMock::getDto([
            'update_id' => time(),
            'message' => [
                'message_id' => 3,
                'message_thread_id' => 5252,
                'from' => ['id' => 999, 'is_bot' => false, 'first_name' => 'Manager'],
                'chat' => ['id' => -100999999, 'type' => 'supergroup'],
                'date' => time(),
                'text' => 'ответ менеджера',
            ],
        ]);

        $params = TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => 'ответ менеджера',
        ]);

        (new SendTelegramMessageJob($botUser->id, $dto, $params, 'outgoing', $this->mockTelegram()))->handle();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'message_type' => 'outgoing',
            'text' => 'ответ менеджера',
        ]);
    }
}
