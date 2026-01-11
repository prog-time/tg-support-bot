<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\Stubs\Services\External\ExternalServiceStub;
use Tests\TestCase;

class ExternalServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $source = 'live_chat';
        $external_id = time();
        $text = 'Тестовое сообщение';

        $this->basicPayload = [
            'source' => $source,
            'external_id' => $external_id,
            'text' => $text,
        ];
    }

    public function test_construct_with_private_source(): void
    {
        $request = Request::create('api/telegram/bot', 'POST', $this->basicPayload);
        $dto = ExternalMessageDto::fromRequest($request);

        $service = new ExternalServiceStub($dto);

        $this->assertNotNull($service->getBotUser());
    }
}
