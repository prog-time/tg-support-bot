<?php

namespace Tests\Unit\Services\ActionService\Edit;

use Tests\Stubs\Services\ActionService\Edit\FromTgEditServiceStub;
use Tests\TestCase;

class FromTgEditServiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_construct_with_private_source(): void
    {
        $service = new FromTgEditServiceStub();

        $this->assertEquals('telegram', $service->getSource());
    }
}
