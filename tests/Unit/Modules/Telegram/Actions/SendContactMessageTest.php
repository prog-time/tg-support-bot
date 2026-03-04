<?php

namespace Tests\Unit\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\Actions\SendContactMessage;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendContactMessageTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(time(), 'telegram');
    }

    public function test_send_contact_message(): void
    {
        app(SendContactMessage::class)->execute($this->botUser);

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramSimpleQueryJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        // Assert
        $this->assertEquals(config('traffic_source.settings.telegram.group_id'), $job->queryParams->chat_id);
        $this->assertEquals('sendMessage', $job->queryParams->methodQuery);
    }
}
