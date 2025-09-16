<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetChat;
use Tests\TestCase;

class GetChatTest extends TestCase
{
    public int $chatId;

    public function setUp(): void
    {
        parent::setUp();

        $this->chatId = config('testing.tg_private.chat_id');
    }

    public function test_get_chat(): void
    {
        $result = GetChat::execute($this->chatId);

        $this->assertNotEmpty($result->rawData);
        $this->assertTrue($result->ok);
    }
}
