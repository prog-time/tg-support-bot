<?php

namespace Tests\Unit\Services\WebPush;

use App\Services\WebPush\WebPushSenderInterface;
use App\Services\WebPush\WebPushSendResult;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WebPushSenderInterfaceTest extends TestCase
{
    public function test_declares_a_send_method_with_the_expected_signature(): void
    {
        $reflection = new ReflectionClass(WebPushSenderInterface::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('send'));

        $method = $reflection->getMethod('send');
        $parameters = $method->getParameters();

        $this->assertSame('subscription', $parameters[0]->getName());
        $this->assertSame('title', $parameters[1]->getName());
        $this->assertSame('body', $parameters[2]->getName());
        $this->assertSame(WebPushSendResult::class, (string) $method->getReturnType());
    }
}
