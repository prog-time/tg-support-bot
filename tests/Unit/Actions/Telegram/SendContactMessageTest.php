<?php

namespace Tests\Unit\Actions\Telegram;

use App\Actions\Telegram\SendContactMessage;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
    }

    public function test_send_contact_message(): void
    {
        (new SendContactMessage())->execute($this->botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
