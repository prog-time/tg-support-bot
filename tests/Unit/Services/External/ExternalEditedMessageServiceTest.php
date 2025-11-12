<?php

namespace Tests\Unit\Services\External;

use App\DTOs\External\ExternalMessageDto;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Tests\TestCase;

class ExternalEditedMessageServiceTest extends TestCase
{
    public string $source;

    public string $external_id;

    public string $text = 'Тестовое сообщение';

    public int $messageId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');
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
        Message::truncate();

        // создание сообщения
        $dataMessage = $this->getMessageParams();
        (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));

        // получаем созданное сообщение
        $message = Message::where([
            'platform' => $dataMessage['source'],
            'message_type' => 'incoming',
        ])->first();

        $this->assertNotEmpty($message);

        // отправляем сообщение
        $dataUpdateMessage = array_merge($dataMessage, [
            'message_id' => $message->from_id,
            'text' => 'Изменил сообщение!',
        ]);
        (new ExternalTrafficService())->update(ExternalMessageDto::from($dataUpdateMessage));

        $updateMessage = Message::where([
            'platform' => $dataUpdateMessage['source'],
            'message_type' => 'incoming',
            'from_id' => $message->from_id,
        ])->first();

        $this->assertNotEmpty($updateMessage);
        $this->assertEquals($updateMessage->externalMessage->text, $dataUpdateMessage['text']);
    }
}
