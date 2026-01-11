<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendBannedMessage;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'tg');
    }

    public function test_send_ban_message(): void
    {
        (new SendBannedMessage())->execute($this->botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals($this->botUser->chat_id, $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
