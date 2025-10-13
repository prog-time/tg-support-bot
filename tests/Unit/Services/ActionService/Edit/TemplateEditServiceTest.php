<?php

namespace Tests\Unit\Services\ActionService\Edit;

use Tests\Stubs\Services\ActionService\Edit\TemplateEditServiceStub;
use Tests\TestCase;

class TemplateEditServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_construct_with_private_source(): void
    {
        $service = new TemplateEditServiceStub();

        $this->assertEquals('telegram', $service->getSource());
    }
}
