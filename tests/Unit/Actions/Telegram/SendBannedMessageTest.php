<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendBannedMessage;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendBannedMessageTest extends TestCase
{
    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'tg');
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
