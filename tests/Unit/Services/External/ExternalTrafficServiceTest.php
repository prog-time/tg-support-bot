<?php

namespace Tests\Unit\Services\External;

use App\Actions\External\DeleteMessage;
use App\DTOs\External\ExternalListMessageDto;
use App\DTOs\External\ExternalMessageDto;
use App\Jobs\SendMessage\SendExternalTelegramMessageJob;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\Services\External\ExternalTrafficService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\External\ExternalMessageDtoMock;
use Tests\TestCase;

class ExternalTrafficServiceTest extends TestCase
{
    private mixed $source;

    private mixed $external_id;

    private BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        Queue::fake();

        $this->source = config('testing.external.source');
        $this->external_id = config('testing.external.external_id');

        $externalUser = ExternalUser::firstOrCreate([
            'external_id' => $this->external_id,
            'source' => $this->source,
        ]);

        $this->botUser = BotUser::firstOrCreate([
            'chat_id' => $externalUser->id,
            'platform' => $this->source,
        ]);
    }

    public function createMessage(): Message
    {
        // Сохраняем сообщения в БД
        $whereMessageParams = [
            'bot_user_id' => $this->botUser->id,
            'message_type' => 'incoming',
            'platform' => $this->source,
            'from_id' => rand(),
            'to_id' => rand(),
        ];
        $createdMessage = Message::where($whereMessageParams)->firstOrCreate($whereMessageParams);

        $createdMessage->externalMessage()->create([
            'text' => 'Тестовое сообщение',
            'file_id' => null,
        ]);

        return $createdMessage;
    }

    public function test_get_list_messages(): void
    {
        $this->createMessage();

        // получаем список сообщений
        $filterDto = ExternalListMessageDto::from([
            'external_id' => $this->external_id,
            'source' => $this->source,
        ]);

        $result = (new ExternalTrafficService())->list($filterDto);

        $this->assertIsArray($result['messages']);
        $this->assertNotEmpty($result['messages']);
    }

    public function test_get_list_messages_error_messages_not_found(): void
    {
        $filterDto = ExternalListMessageDto::from([
            'external_id' => 'not_exist',
            'source' => $this->source,
        ]);

        $result = (new ExternalTrafficService())->list($filterDto);

        $this->assertIsArray($result);
        $this->assertFalse($result['status']);
        $this->assertEquals('Чат не найден!', $result['error']);
    }

    public function test_show(): void
    {
        $this->createMessage();

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

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendExternalTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        $job = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $job->botUser->id);
        $this->assertEquals('sendDocument', $job->queryParams->methodQuery, );
        $this->assertEquals('private', $job->queryParams->typeSource, );
        $this->assertEquals($job->queryParams->message_thread_id, $this->botUser->topic_id);
    }

    public function test_destroy(): void
    {
        $message = $this->createMessage();

        $payload = ExternalMessageDtoMock::getDtoParams();
        $payload['message_id'] = $message->from_id;

        // удаляем сообщение
        $deleteDto = ExternalMessageDtoMock::getDto($payload);

        (new ExternalTrafficService())->destroy($deleteDto);

        DeleteMessage::execute($deleteDto);

        $this->assertNull(Message::first());
    }
}
