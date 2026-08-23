<?php

namespace Tests\Unit\Observers;

use App\Jobs\SendWebPushNotificationJob;
use App\Models\BotUser;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MessageObserverTest extends TestCase
{
    use RefreshDatabase;

    private function makeBotUser(): BotUser
    {
        return BotUser::create(['chat_id' => 2002, 'platform' => 'telegram']);
    }

    public function test_dispatches_web_push_job_for_an_incoming_message(): void
    {
        Bus::fake();

        $botUser = $this->makeBotUser();

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 2002,
            'to_id' => 0,
            'text' => 'hi',
        ]);

        Bus::assertDispatched(
            SendWebPushNotificationJob::class,
            fn (SendWebPushNotificationJob $job) => $job->message->is($message)
        );
    }

    public function test_does_not_dispatch_web_push_job_for_an_outgoing_message(): void
    {
        Bus::fake();

        $botUser = $this->makeBotUser();

        Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 2002,
            'text' => 'reply',
        ]);

        Bus::assertNotDispatched(SendWebPushNotificationJob::class);
    }
}
