<?php

namespace Tests\Unit\Services\WebPush;

use App\Services\WebPush\WebPushSendResult;
use PHPUnit\Framework\TestCase;

class WebPushSendResultTest extends TestCase
{
    public function test_stores_success_flags(): void
    {
        $result = new WebPushSendResult(success: true, expired: false);

        $this->assertTrue($result->success);
        $this->assertFalse($result->expired);
    }

    public function test_stores_expired_flags(): void
    {
        $result = new WebPushSendResult(success: false, expired: true);

        $this->assertFalse($result->success);
        $this->assertTrue($result->expired);
    }
}
