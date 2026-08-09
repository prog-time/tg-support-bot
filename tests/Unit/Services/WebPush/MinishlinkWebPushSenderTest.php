<?php

namespace Tests\Unit\Services\WebPush;

use App\Models\PushSubscription;
use App\Services\Settings\SettingsService;
use App\Services\WebPush\MinishlinkWebPushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinishlinkWebPushSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_safe_failure_when_vapid_keys_are_not_configured(): void
    {
        $subscription = new PushSubscription([
            'endpoint' => 'https://push.example.com/x',
            'public_key' => 'p256dh',
            'auth_token' => 'auth',
        ]);

        $sender = new MinishlinkWebPushSender(app(SettingsService::class));

        $result = $sender->send($subscription, 'Title', 'Body');

        $this->assertFalse($result->success);
        $this->assertFalse($result->expired);
    }
}
