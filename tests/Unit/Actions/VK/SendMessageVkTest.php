<?php

namespace Tests\Unit\Actions\VK;

use App\Actions\VK\SendMessageVk;
use App\DTOs\VK\VkTextMessageDto;
use Tests\TestCase;

class SendMessageVkTest extends TestCase
{
    private int $chatId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = (int)config('testing.vk_private.chat_id');
    }

    public function test_send_text_message(): void
    {
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
