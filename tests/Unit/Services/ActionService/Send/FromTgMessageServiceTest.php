<?php

namespace Tests\Unit\Services\ActionService\Send;

use App\DTOs\TelegramUpdateDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\Stubs\Services\ActionService\Send\FromTgMessageServiceStub;
use Tests\TestCase;

class FromTgMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $this->basicPayload = [
            'update_id' => time(),
            'message' => [
                'message_id' => time(),
                'from' => [
                    'id' => time(),
                    'is_bot' => false,
                    'first_name' => 'Test',
                    'last_name' => 'Testov',
                    'username' => 'usertest',
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => time(),
                    'first_name' => 'Test',
                    'last_name' => 'Testov',
                    'username' => 'usertest',
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => 'Тестовое сообщение',
            ],
        ];
    }

    public function test_construct_with_private_source(): void
    {
        $request = Request::create('api/telegram/bot', 'POST', $this->basicPayload);
        $dto = TelegramUpdateDto::fromRequest($request);

        $service = new FromTgMessageServiceStub($dto);

        $this->assertEquals('telegram', $service->getSource());
        $this->assertEquals('incoming', $service->getTypeMessage());

        $this->assertEquals($dto, $service->getUpdate());
    }
}
