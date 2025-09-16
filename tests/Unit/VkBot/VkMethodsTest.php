<?php

namespace Tests\Unit\VkBot;

use App\VkBot\VkMethods;
use Tests\TestCase;

class VkMethodsTest extends TestCase
{
    private int $chatId;

    public function setUp(): void
    {
        parent::setUp();
        $this->chatId = (int)config('testing.vk_private.chat_id');
    }

    public function test_send_text_message(): void
    {
        $result = VkMethods::sendQueryVk('messages.send', [
            'peer_id' => $this->chatId,
            'message' => 'Тестовое сообщение',
        ]);

        $this->assertEquals($result->response_code, 200);
        $this->assertNotEmpty($result->response);
        $this->assertIsInt($result->response);
    }
}
