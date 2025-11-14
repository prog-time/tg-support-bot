<?php

namespace Tests\Unit\Actions\External;

use App\Actions\External\DeleteMessage;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class DeleteMessageTest extends TestCase
{
    public function test_delete_message(): void
    {
        Message::truncate();

        // отправляем сообщение
        (new ExternalTrafficService())->store(ExternalMessageDtoMock::getDto());

        // получаем сообщение
        $messageData = Message::first();
        $payload = ExternalMessageDtoMock::getDtoParams();
        $payload['message_id'] = $messageData->from_id;

        // удаляем сообщение
        $dto = ExternalMessageDtoMock::getDto($payload);
        DeleteMessage::execute($dto);

        $messageData = Message::first();

        $this->assertNull($messageData);
    }
}
