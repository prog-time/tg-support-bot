<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendContactMessage;
use App\Models\BotUser;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
    }

    public function test_create_contact_message(): void
    {
        $currentTextMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
        $currentTextMessage .= "Источник: {$this->botUser->platform} \n";
        $currentTextMessage .= "ID: {$this->botUser->chat_id} \n";
        $currentTextMessage .= "Ссылка: https://telegram.me/iliyalyachuk \n";

        $textMessage = (new SendContactMessage())->createContactMessage($this->botUser);

        $this->assertEquals($currentTextMessage, $textMessage);
    }
}
