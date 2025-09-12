<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\GetChat;
use App\Models\BotUser;
use Tests\TestCase;

class GetChatTest extends TestCase
{
    public int $userChatId = 1424646511;

    public function botTestUser(): BotUser
    {
        return BotUser::where('chat_id', $this->userChatId)->first();
    }

    public function test_get_chat(): void
    {
        $botUser = $this->botTestUser();

        $result = GetChat::execute($botUser->chat_id);

        $this->assertNotEmpty($result->rawData);
        $this->assertTrue($result->ok);
    }
}
