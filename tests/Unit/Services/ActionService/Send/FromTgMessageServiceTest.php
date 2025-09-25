<?php

namespace Tests\Unit\Services\ActionService\Send;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use Illuminate\Support\Facades\Request;
use Tests\Stubs\Services\ActionService\Send\FromTgMessageServiceStub;
use Tests\TestCase;

class FromTgMessageServiceTest extends TestCase
{
    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        $this->basicPayload = [
            'update_id' => time(),
            'message' => [
                'message_id' => time(),
                'from' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'is_bot' => false,
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
                    'language_code' => 'ru',
                ],
                'chat' => [
                    'id' => config('testing.tg_private.chat_id'),
                    'first_name' => config('testing.tg_private.first_name'),
                    'last_name' => config('testing.tg_private.last_name'),
                    'username' => config('testing.tg_private.username'),
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
        $botUser = BotUser::where('chat_id', config('testing.tg_private.chat_id'))->first();

        $service = new FromTgMessageServiceStub($dto);

        $this->assertEquals('telegram', $service->getSource());
        $this->assertEquals('incoming', $service->getTypeMessage());

        $this->assertEquals($botUser, $service->getBotUser());
        $this->assertEquals($dto, $service->getUpdate());
    }
}
