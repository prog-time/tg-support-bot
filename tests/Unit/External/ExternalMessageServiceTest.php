<?php

namespace Tests\Unit\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\Services\External\ExternalTrafficService;
use Tests\TestCase;

class ExternalMessageServiceTest extends TestCase
{
    public string $source = 'live_chat';

    public string $external_id = 'test_chat';

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

        $this->assertNotEmpty($response->message_id);
        $this->assertIsInt($response->message_id);
    }

    public function test_delete_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $responseSend = $this->sendNewMessage($dataMessage);

        $dataMessage['message_id'] = $responseSend->message_id;
        $responseDelete = (new DeleteMessage())->execute(ExternalMessageDto::from($dataMessage));

        $this->assertNotEmpty($responseDelete->message_id);
        $this->assertIsInt($responseDelete->message_id);
    }

    public function test_edit_external_message(): void
    {
        $newText = 'Изменил сообщение!';

        $dataMessage = $this->getMessageParams();
        $responseSend = $this->sendNewMessage($dataMessage);

        $dataMessage['message_id'] = $responseSend->message_id;
        $dataMessage['text'] = $newText;
        $responseUpdate = (new ExternalTrafficService())->update(ExternalMessageDto::from($dataMessage));

        $this->assertNotEmpty($responseUpdate->message_id);
        $this->assertIsInt($responseUpdate->message_id);
    }

    public function test_list_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $this->sendNewMessage($dataMessage);

        $responseListMessage = (new ExternalTrafficService())->list(ExternalListMessageDto::from($dataMessage));

        $this->assertIsArray($responseListMessage);
        $this->assertArrayHasKey('source', $responseListMessage);
        $this->assertIsString($responseListMessage['source']);

        $this->assertArrayHasKey('external_id', $responseListMessage);
        $this->assertIsString($responseListMessage['external_id']);

        $this->assertArrayHasKey('messages', $responseListMessage);
        $this->assertIsArray($responseListMessage['messages']);
    }
}
