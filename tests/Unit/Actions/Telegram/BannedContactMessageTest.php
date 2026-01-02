<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\BannedContactMessage;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BannedContactMessageTest extends TestCase
{
    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'tg');
    }

    public function test_ban_status_true(): void
    {
        (new BannedContactMessage())->execute($this->botUser, true);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
