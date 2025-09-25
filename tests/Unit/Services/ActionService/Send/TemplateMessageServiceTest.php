<?php

namespace Tests\Unit\Services\ActionService\Send;

use Tests\Stubs\Services\ActionService\Send\TemplateMessageServiceStub;
use Tests\TestCase;

class TemplateMessageServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_construct_with_private_source(): void
    {
        $service = new TemplateMessageServiceStub();

        $this->assertEquals('telegram', $service->getSource());
    }
}
