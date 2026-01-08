<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetChat;
use App\DTOs\TelegramAnswerDto;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class GetChatTest extends TestCase
{
    public int $chatId;

    public function setUp(): void
    {
        parent::setUp();

        $this->chatId = time();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_chat(): void
    {
        $data = [
            'ok' => true,
            'result' => [
                'id' => $this->chatId,
                'type' => 'private',
                'title' => 'Test Chat',
                'username' => 'testuser',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        ];

        $expectedDto = TelegramAnswerDto::fromData($data);

        Http::fake([
            'https://api.telegram.org/*/getChat*' => Http::response($data, 200),
        ]);

        $result = GetChat::execute($this->chatId);

        $this->assertInstanceOf(TelegramAnswerDto::class, $result);
        $this->assertNotEmpty($result->rawData);
        $this->assertTrue($result->rawData['ok']);
        $this->assertEquals($this->chatId, $result->rawData['result']['id']);
    }
}
