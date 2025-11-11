<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendContactMessage;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    private BotUser $botUser;

    private SendContactMessage $sendContactMessage;

    public int $chatId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatId = config('testing.tg_private.chat_id');
        $this->sendContactMessage = new SendContactMessage();

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

    public function test_execute_by_bot_user(): void
    {
        $result = $this->sendContactMessage->executeByBotUser($this->botUser);
        $this->assertTrue($result->ok);
    }

    public function test_create_contact_message(): void
    {
        $currentTextMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
        $currentTextMessage .= "Источник: {$this->botUser->platform} \n";
        $currentTextMessage .= "ID: {$this->botUser->chat_id} \n";
        $currentTextMessage .= "Ссылка: https://telegram.me/iliyalyachuk \n";

        $textMessage = $this->sendContactMessage->createContactMessage($this->botUser);

        $this->assertEquals($currentTextMessage, $textMessage);
    }
}
