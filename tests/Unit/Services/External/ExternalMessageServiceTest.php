<?php

namespace Tests\Unit\Services\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageDto;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Tests\TestCase;

class ExternalMessageServiceTest extends TestCase
{
    public string $source;

    public string $external_id;

    public string $text;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();

        $this->text = 'Тестовое сообщение';

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

    public function test_send_external_message(): void
    {
        $dataMessage = $this->getMessageParams();
        (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));

        $message = Message::where([
            'platform' => $dataMessage['source'],
            'message_type' => 'incoming',
        ])->orderBy('id', 'desc')->first();

        $this->assertNotNull($message);
        $this->assertEquals($message->platform, $dataMessage['source']);

        $this->assertNotNull($message->botUser->externalUser);
        $this->assertEquals($message->botUser->externalUser->external_id, $dataMessage['external_id']);

        $this->assertNotNull($message->externalMessage);
        $this->assertEquals($message->externalMessage->text, $dataMessage['text']);
    }

    public function test_delete_external_message(): void
    {
        Message::truncate();

        $dataMessage = $this->getMessageParams();
        (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));

        $message = Message::where([
            'platform' => $dataMessage['source'],
            'message_type' => 'incoming',
        ])->orderBy('id', 'desc')->first();

        $this->assertNotEmpty($message);

        $dataMessage['message_id'] = $message->from_id;

        (new DeleteMessage())->execute(ExternalMessageDto::from($dataMessage));

        $message = Message::first();

        $this->assertEmpty($message);
    }

    public function test_edit_external_message(): void
    {
        Message::truncate();

        $dataMessage = $this->getMessageParams();
        (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));

        $message = Message::where([
            'platform' => $dataMessage['source'],
            'message_type' => 'incoming',
        ])->orderBy('id', 'desc')->first();

        $this->assertNotEmpty($message);

        $newText = 'Изменил сообщение!';
        $dataUpdateMessage = array_merge($dataMessage, [
            'message_id' => $message->from_id,
            'text' => $newText,
        ]);
        (new ExternalTrafficService())->update(ExternalMessageDto::from($dataUpdateMessage));

        $updateMessage = Message::where([
            'platform' => $dataMessage['source'],
            'message_type' => 'incoming',
            'from_id' => $message->from_id,
        ])->first();

        $this->assertNotEmpty($updateMessage);
        $this->assertEquals($updateMessage->externalMessage->text, $dataUpdateMessage['text']);
    }

    public function test_list_external_message(): void
    {
        Message::truncate();

        $dataMessage = $this->getMessageParams();
        (new ExternalTrafficService())->store(ExternalMessageDto::from($dataMessage));

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
