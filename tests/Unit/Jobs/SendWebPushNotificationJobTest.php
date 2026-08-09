<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendWebPushNotificationJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPush\WebPushSenderInterface;
use App\Services\WebPush\WebPushSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendWebPushNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(string $text = 'Hello there'): Message
    {
        $botUser = BotUser::create(['chat_id' => 1001, 'platform' => 'telegram']);

        return Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => 'telegram',
            'message_type' => 'incoming',
            'from_id' => 1001,
            'to_id' => 0,
            'text' => $text,
        ]);
    }

    private function makeSubscription(): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => User::factory()->create()->id,
            'endpoint' => 'https://push.example.com/' . uniqid(),
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-token',
        ]);
    }

    public function test_does_nothing_when_there_are_no_subscriptions(): void
    {
        $sender = new class () implements WebPushSenderInterface {
            public int $calls = 0;

            public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult
            {
                $this->calls++;

                return new WebPushSendResult(success: true, expired: false);
            }
        };

        (new SendWebPushNotificationJob($this->makeMessage()))->handle($sender);

        $this->assertSame(0, $sender->calls);
    }

    public function test_sends_to_every_subscription_with_the_message_text(): void
    {
        $this->makeSubscription();
        $this->makeSubscription();
        $message = $this->makeMessage('New message from the customer');

        $received = [];
        $sender = new class ($received) implements WebPushSenderInterface {
            public array $received = [];

            public function __construct(&$received)
            {
                $this->received = &$received;
            }

            public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult
            {
                $this->received[] = [$title, $body];

                return new WebPushSendResult(success: true, expired: false);
            }
        };

        (new SendWebPushNotificationJob($message))->handle($sender);

        $this->assertCount(2, $received);
        $this->assertSame('New message from the customer', $received[0][1]);
    }

    public function test_deletes_the_subscription_when_the_service_reports_it_expired(): void
    {
        $subscription = $this->makeSubscription();

        $sender = new class () implements WebPushSenderInterface {
            public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult
            {
                return new WebPushSendResult(success: false, expired: true);
            }
        };

        (new SendWebPushNotificationJob($this->makeMessage()))->handle($sender);

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_keeps_the_subscription_on_a_transient_failure(): void
    {
        $subscription = $this->makeSubscription();

        $sender = new class () implements WebPushSenderInterface {
            public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult
            {
                return new WebPushSendResult(success: false, expired: false);
            }
        };

        (new SendWebPushNotificationJob($this->makeMessage()))->handle($sender);

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);
    }
}
