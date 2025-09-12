<?php

namespace Tests\Unit\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Tests\TestCase;

class ExternalMessageServiceTest extends TestCase
{
    public string $source = 'live_chat';

    public string $external_id = 'wPsYu0HOXsuK';

    public string $text = 'Тестовое сообщение';

    protected function getMessageParams(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => $this->text,
        ];
    }

    protected function sendNewMessage(array $dataMessage): ExternalMessageAnswerDto
    {
        return (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));
    }

    public function test_send_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $response = $this->sendNewMessage($dataMessage);

        $this->assertNotEmpty($response->result->message_id);
        $this->assertIsInt($response->result->message_id);
    }

    public function test_delete_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $responseSend = $this->sendNewMessage($dataMessage);

        $messageData = Message::where([
            'to_id' => $responseSend->result->message_id,
        ])->first();

        $this->assertNotEmpty($messageData->from_id);

        $dataMessage['message_id'] = $messageData->from_id;

        $responseDelete = (new DeleteMessage())->execute(ExternalMessageDto::from($dataMessage));

        $this->assertNotEmpty($responseDelete->status);
        $this->assertIsBool($responseDelete->status);
    }

    public function test_not_delete_external_message(): void
    {
        // Arrange: подготавливаем данные с некорректным message_id
        $dataMessage = $this->getMessageParams();
        $invalidMessageData = array_merge($dataMessage, [
            'message_id' => 0,
        ]);

        // Act: выполняем операцию удаления
        $responseDelete = (new DeleteMessage())->execute(
            ExternalMessageDto::from($invalidMessageData)
        );

        // Assert: проверяем, что операция завершилась неудачей
        $this->assertFalse($responseDelete->status, 'Status should be false when message_id is invalid');
        $this->assertIsBool($responseDelete->status, 'Status should be boolean type');

        // Дополнительные проверки (опционально)
        if (property_exists($responseDelete, 'error')) {
            $this->assertNotEmpty($responseDelete->error, 'Error message should be provided');
        }
    }

    public function test_edit_external_message(): void
    {
        $newText = 'Изменил сообщение!';

        $dataMessage = $this->getMessageParams();
        $responseSend = $this->sendNewMessage($dataMessage);

        $messageData = Message::where([
            'to_id' => $responseSend->result->message_id,
        ])->first();

        $this->assertNotEmpty($messageData->from_id);

        $responseUpdate = (new ExternalTrafficService())->update(ExternalMessageDto::from(array_merge($dataMessage, [
            'message_id' => $messageData->from_id,
            'text' => $newText,
        ])));

        $this->assertNotEmpty($responseUpdate->status);
        $this->assertIsBool($responseUpdate->status);
    }

    public function test_list_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $this->sendNewMessage($dataMessage);

        $responseListMessage = (new ExternalTrafficService())->list(ExternalListMessageDto::from($dataMessage));

        $this->assertNotEmpty($responseListMessage);
        $this->assertTrue($responseListMessage['status']);

        $this->assertArrayHasKey('source', $responseListMessage);
        $this->assertIsString($responseListMessage['source']);

        $this->assertArrayHasKey('external_id', $responseListMessage);
        $this->assertIsString($responseListMessage['external_id']);

        $this->assertArrayHasKey('messages', $responseListMessage);
        $this->assertIsArray($responseListMessage['messages']);
    }
}
