<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendContactMessage;
use App\Models\BotUser;
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

        if (BotUser::where('chat_id', $this->chatId)->exists()) {
            $this->botUser = BotUser::where('chat_id', $this->chatId)->first();
        } else {
            $this->botUser = BotUser::create([
                'chat_id' => $this->chatId,
                'topic_id' => 0,
                'platform' => 'telegram',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
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

    public function test__execute_by_tg_update(): void
    {
        $result = $this->sendContactMessage->executeByChatId($this->chatId);
        $this->assertTrue($result->ok);
    }
}
