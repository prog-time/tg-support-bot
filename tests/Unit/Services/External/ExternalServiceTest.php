<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageDto;
use Illuminate\Support\Facades\Request;
use Tests\Stubs\Services\External\ExternalServiceStub;
use Tests\TestCase;

class ExternalServiceTest extends TestCase
{
    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $source = config('testing.external.source');
        $external_id = config('testing.external.external_id');
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
