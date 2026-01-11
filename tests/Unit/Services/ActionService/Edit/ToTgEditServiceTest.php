<?php

namespace Tests\Unit\Services\ActionService\Edit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\Stubs\Services\ActionService\Edit\ToTgEditServiceStub;
use Tests\TestCase;

class ToTgEditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
    }

    public function test_construct(): void
    {
        $dto = TelegramUpdateDtoMock::getDto();

        $service = new ToTgEditServiceStub($dto);

        $this->assertEquals('telegram', $service->getSource());
    }
}
