<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\BanMessage;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use Tests\TestCase;

class BanMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = $this->botTestUser();

        TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $this->botUser->topic_id,
            'text' => 'Тестовое сообщение',
        ]);
    }

    public function botTestUser(): BotUser
    {
        return BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
    }

    public function test_send_ban_message_with_correct_text(): void
    {
        // Получаем ожидаемый текст сообщения
        $expectedMessage = __('messages.ban_bot');

        // Act
        $result = BanMessage::execute($this->botUser->topic_id);

        // Assert
        $this->assertTrue($result->ok);
        $this->assertEquals($expectedMessage, $result->text);
    }
}
