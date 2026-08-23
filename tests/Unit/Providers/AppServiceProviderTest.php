<?php

namespace Tests\Unit\Providers;

use App\Services\WebPush\MinishlinkWebPushSender;
use App\Services\WebPush\WebPushSenderInterface;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_web_push_sender_interface_resolves_to_the_minishlink_implementation(): void
    {
        $this->assertInstanceOf(MinishlinkWebPushSender::class, app(WebPushSenderInterface::class));
    }
}
