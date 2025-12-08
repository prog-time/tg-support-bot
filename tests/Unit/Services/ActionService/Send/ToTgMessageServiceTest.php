<?php

namespace Tests\Unit\Services\ActionService\Send;

use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\Stubs\Services\ActionService\Send\ToTgMessageServiceStub;
use Tests\TestCase;

class ToTgMessageServiceTest extends TestCase
{
    public function test_construct_with_private_source(): void
    {
        $dto = TelegramUpdateDtoMock::getDto([
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
        ]);

        $service = new ToTgMessageServiceStub($dto);

        $this->assertEquals('telegram', $service->getSource());
        $this->assertEquals('incoming', $service->getTypeMessage());

        $this->assertEquals($dto, $service->getUpdate());
    }
}
