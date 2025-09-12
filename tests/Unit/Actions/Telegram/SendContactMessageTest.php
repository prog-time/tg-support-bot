<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendContactMessage;
use App\Models\BotUser;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    private SendContactMessage $sendContactMessage;

    public int $userChatId = 1424646511;

    public function botTestUser(): BotUser
    {
        return BotUser::where('chat_id', $this->userChatId)->first();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sendContactMessage = new SendContactMessage();
    }

    public function test_execute_by_bot_user(): void
    {
        // Arrange
        $botUser = $this->botTestUser();

        // Act
        $result = $this->sendContactMessage->executeByBotUser($botUser);

        // Assert
        $this->assertTrue($result->ok);
    }

    public function test_create_contact_message(): void
    {
        // Arrange
        $botUser = $this->botTestUser();
        $currentTextMessage = "<b>КОНТАКТНАЯ ИНФОРМАЦИЯ</b> \n";
        $currentTextMessage .= "Источник: {$botUser->platform} \n";
        $currentTextMessage .= "ID: {$botUser->chat_id} \n";
        $currentTextMessage .= "Ссылка: https://telegram.me/iliyalyachuk \n";

        // Act
        $textMessage = $this->sendContactMessage->createContactMessage($botUser);

        // Assert
        $this->assertEquals($currentTextMessage, $textMessage);
    }

    public function test__execute_by_tg_update(): void
    {
        // Arrange
        $chatId = $this->userChatId;

        // Act
        $result = $this->sendContactMessage->executeByChatId($chatId);

        // Assert
        $this->assertTrue($result->ok);
    }
}
