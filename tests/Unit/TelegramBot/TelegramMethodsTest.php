<?php

namespace Tests\Unit\TelegramBot;

use App\TelegramBot\TelegramMethods;
use Tests\TestCase;

class TelegramMethodsTest extends TestCase
{
    private int $chatId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatId = config('testing.tg_private.chat_id');
    }

    protected function getMessageParams(): array
    {
        return [
            'chat_id' => $this->chatId,
        ];
    }

    public function test_send_text_message(): void
    {
        $testMessage = 'Тестовое сообщение';

        $queryParams = array_merge($this->getMessageParams(), [
            'text' => $testMessage,
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendMessage', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertNotEmpty($resultQuery->response_code);
        $this->assertEquals($testMessage, $resultQuery->text);
    }

    public function test_send_document_and_caption(): void
    {
        $testMessage = 'Тестовое сообщение';

        $queryParams = array_merge($this->getMessageParams(), [
            'caption' => $testMessage,
            'document' => config('testing.tg_private.file_id'),
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendDocument', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertEquals($resultQuery->response_code, 200);
        $this->assertEquals($testMessage, $resultQuery->text);
    }

    public function test_send_photo_and_caption(): void
    {
        $testMessage = 'Тестовое сообщение';

        $queryParams = array_merge($this->getMessageParams(), [
            'caption' => $testMessage,
            'photo' => config('testing.tg_private.photo_id'),
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendPhoto', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertEquals($resultQuery->response_code, 200);
        $this->assertEquals($testMessage, $resultQuery->text);
    }
}
