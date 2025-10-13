<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageAnswerDto;
use App\DTOs\External\ExternalMessageDto;
use App\DTOs\External\ExternalMessageResponseDto;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Tests\TestCase;

class ExternalEditedMessageServiceTest extends TestCase
{
    public string $source = 'live_chat';

    public string $external_id = 'wPsYu0HOXsuK';

    public string $text = 'Тестовое сообщение';

    public int $messageId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $responseSend = (new ExternalTrafficService())->store(ExternalMessageDto::from([
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => $this->text,
        ]));

        $messageData = Message::where([
            'to_id' => $responseSend->result->to_id,
        ])->first();
        $this->messageId = $messageData->from_id;
    }

    protected function getMessageParams(): array
    {
        return [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => $this->text,
        ];
    }

    public function test_edit_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        $responseUpdate = (new ExternalTrafficService())->update(ExternalMessageDto::from(array_merge($dataMessage, [
            'message_id' => $this->messageId,
            'text' => 'Изменил сообщение!',
        ])));

        $this->assertInstanceOf(ExternalMessageAnswerDto::class, $responseUpdate);

        $this->assertNotEmpty($responseUpdate->status);
        $this->assertIsBool($responseUpdate->status);

        $this->assertInstanceOf(ExternalMessageResponseDto::class, $responseUpdate->result);
    }
}
