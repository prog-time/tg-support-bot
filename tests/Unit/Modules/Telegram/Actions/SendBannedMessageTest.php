<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\SendBannedMessage;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
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
        app(SendBannedMessage::class)->execute($this->botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals($this->botUser->chat_id, $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
