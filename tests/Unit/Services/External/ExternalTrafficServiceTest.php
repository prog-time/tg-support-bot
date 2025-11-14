<?php

namespace Tests\Unit\Services\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageDto;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Illuminate\Http\UploadedFile;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class ExternalTrafficServiceTest extends TestCase
{
    private mixed $source;

    private mixed $external_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');
    }

    public function test_get_list_messages(): void
    {
        // отправляем сообщение
        $dataMessage = [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => 'Тестовое сообщение',
        ];
        $externalDto = ExternalMessageDto::from($dataMessage);
        (new ExternalTrafficService())->store($externalDto);
        // -------------

        // получаем список сообщений
        $filterDto = ExternalListMessageDto::from([
            'external_id' => $this->external_id,
            'source' => $this->source,
        ]);

        $service = new ExternalTrafficService();
        $result = $service->list($filterDto);

        $this->assertIsArray($result['messages']);
        $this->assertNotEmpty($result['messages']);
    }

    public function test_get_list_messages_error_messages_not_found(): void
    {
        $filterDto = ExternalListMessageDto::from([
            'external_id' => 'not_exist',
            'source' => config('testing.external.source'),
        ]);

        $service = new ExternalTrafficService();
        $result = $service->list($filterDto);

        $this->assertIsArray($result);
        $this->assertFalse($result['status']);
        $this->assertEquals('Чат не найден!', $result['error']);
    }

    public function test_show(): void
    {
        // отправляем сообщение
        $dataMessage = [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => 'Тестовое сообщение',
        ];
        $externalDto = ExternalMessageDto::from($dataMessage);
        (new ExternalTrafficService())->store($externalDto);
        // -------------

        $message = Message::where([
            'platform' => $this->source,
            'message_type' => 'incoming',
        ])->orderBy('id', 'desc')->first();

        $result = (new ExternalTrafficService())->show($message->id);

        $this->assertEquals($result->platform, $this->source);
        $this->assertEquals($result->message_type, $message->message_type);
    }

    public function test_send_file(): void
    {
        // отправляем сообщение
        $dataMessage = [
            'source' => $this->source,
            'external_id' => $this->external_id,
            'text' => 'Тестовое сообщение',
            'uploaded_file' => UploadedFile::fake()->create('image.jpg', 100, 'image/jpeg'),
        ];
        $externalDto = ExternalMessageDto::from($dataMessage);

        (new ExternalTrafficService())->sendFile($externalDto);
        // -------------
    }

    public function test_destroy(): void
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

        (new ExternalTrafficService())->destroy($dto);

        DeleteMessage::execute($dto);

        $messageData = Message::first();

        $this->assertNull($messageData);
    }
}
