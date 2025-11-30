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

        sleep(2);
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
            'document' => 'BQACAgIAAxkBAAIHOmi-0ihwIBW1gZH2kie-2qZ39FKUAAJWhAACLrvxSdnwd0Zd4TtpNgQ',
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
            'photo' => 'AgACAgIAAxkBAAIHO2i-0nqM0rxqaqBPjrcf9937EzNRAAJw-jEbLrv5SSpf9j0qc59iAQADAgADeQADNgQ',
        ]);

        $resultQuery = TelegramMethods::sendQueryTelegram('sendPhoto', $queryParams);

        $this->assertTrue($resultQuery->ok);

        $this->assertEquals($resultQuery->response_code, 200);
        $this->assertEquals($testMessage, $resultQuery->text);
    }
}
