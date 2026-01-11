<?php

namespace Tests\Unit\Actions\Vk;

use App\Actions\Vk\SendMessageVk;
use App\DTOs\Vk\VkTextMessageDto;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendMessageVkTest extends TestCase
{
    private int $chatId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = time();
    }

    public function test_send_text_message(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send' => Http::response([
                'response' => 12345,
            ], 200),
        ]);

        $queryParams = [
            'methodQuery' => 'messages.send',
            'peer_id' => $this->chatId,
            'message' => 'Тестовое сообщение',
        ];
        $result = SendMessageVk::execute(VkTextMessageDto::from($queryParams));

        $this->assertNotEmpty($result->response);
        $this->assertEquals(200, $result->response_code);
    }
}
