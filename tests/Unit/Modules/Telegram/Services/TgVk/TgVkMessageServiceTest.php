<?php

namespace Tests\Unit\Modules\Telegram\Services\TgVk;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Telegram\Services\TgVk\TgVkMessageService;
use App\Modules\Vk\Jobs\SendVkMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgVkMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    protected string $botToken;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();
        BotUser::truncate();

        $this->botUser = BotUser::getUserByChatId(time(), 'vk');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_text_message(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        // проверка отправки сообщения
        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($dto, $jobData->updateDto);
    }

    public function test_send_photo(): void
    {
        $fileName = 'path/file.jpg';
        $tgFileUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$fileName}";
        $vkUploadFileUrl = 'https://vk.com/stub_file/123456789_987654321';

        $uploadFileVkResponse = [
            'server' => 123456,
            'file' => '{"file":"ABCD1234"}',
            'hash' => 'abcdef1234567890',
        ];

        Http::fake([
            // getFile
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'test_file_id',
                    'file_path' => $fileName,
                ],
            ], 200),

            // tg file data
            $tgFileUrl => Http::response(
                'FAKE_BINARY_CONTENT', // тут можно любой контент
                200,
                ['Content-Type' => 'image/jpeg']
            ),

            // get upload server
            'https://api.vk.com/method/photos.getMessagesUploadServer' => Http::response([
                'response' => [
                    'upload_url' => $vkUploadFileUrl,
                ],
            ], 200),

            // upload file to vk
            'https://vk.com/stub_file/*' => Http::response($uploadFileVkResponse, 200),

            // save file
            'https://api.vk.com/method/photos.saveMessagesPhoto*' => Http::response([
                'response' => [
                    [
                        'id' => 1,
                        'owner_id' => 1,
                    ],
                ],
            ], 200),
        ]);

        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            [
                'file_id' => 'test_file_id',
            ],
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        // проверка отправки сообщения
        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($dto, $jobData->updateDto);
        $this->assertNotEmpty($jobData->queryParams->attachment);
    }

    public function test_send_sticker(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['sticker'] = [
            'emoji' => '👍',
            'file_id' => 'test_file_id',
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        // проверка отправки сообщения
        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($dto, $jobData->updateDto);
        $this->assertEquals($dto->rawData['message']['sticker']['emoji'], $jobData->queryParams->message);
    }

    public function test_send_contact(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['contact'] = [
            'phone_number' => '79999999999',
            'first_name' => 'Тестовый',
            'last_name' => 'Тест',
            'user_id' => time(),
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        // проверка отправки сообщения
        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals($dto, $jobData->updateDto);
    }

    public function test_send_text_message_with_buttons(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['text'] = "Выберите вариант:\n[[Да|callback:yes]] [[Нет|callback:no]]\n[[Сайт|url:https://example.com]]";

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertEquals('Выберите вариант:', $jobData->queryParams->message);
        $this->assertNotNull($jobData->queryParams->keyboard);

        $keyboard = json_decode($jobData->queryParams->keyboard, true);
        $this->assertIsArray($keyboard);
        $this->assertArrayHasKey('buttons', $keyboard);
        $this->assertCount(2, $keyboard['buttons']);
    }

    public function test_send_voice(): void
    {
        $fileName = 'path/voice.ogg';
        $tgFileUrl = "https://api.telegram.org/file/bot{$this->botToken}/{$fileName}";
        $vkUploadFileUrl = 'https://vk.com/stub_file/voice_upload';

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id'   => 'voice_file_id',
                    'file_path' => $fileName,
                ],
            ], 200),

            $tgFileUrl => Http::response('FAKE_OGG_CONTENT', 200, ['Content-Type' => 'audio/ogg']),

            'https://api.vk.com/method/docs.getMessagesUploadServer*' => Http::response([
                'response' => ['upload_url' => $vkUploadFileUrl],
            ], 200),

            'https://vk.com/stub_file/*' => Http::response([
                'file' => 'encoded_voice_data',
            ], 200),

            'https://api.vk.com/method/docs.save*' => Http::response([
                'response' => [
                    'type'          => 'audio_message',
                    'audio_message' => ['id' => 42, 'owner_id' => 123],
                ],
            ], 200),
        ]);

        $payload = $this->basicPayload;
        $payload['message']['voice'] = [
            'file_id'   => 'voice_file_id',
            'duration'  => 5,
            'mime_type' => 'audio/ogg',
        ];
        unset($payload['message']['text']);

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];

        $this->assertEquals($this->botUser->id, $jobData->botUserId);
        $this->assertEquals($this->botUser->chat_id, $jobData->queryParams->peer_id);
        $this->assertNotEmpty($jobData->queryParams->attachment);

        // Verify type=audio_message was sent to VK upload server
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'docs.getMessagesUploadServer')
                && $request->data()['type'] === 'audio_message';
        });
    }

    public function test_send_text_message_without_buttons(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['text'] = 'Обычное сообщение без кнопок';

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendVkMessageJob::class];
        $this->assertEquals(1, count($pushed));

        $jobData = $pushed[0]['job'];

        $this->assertEquals('Обычное сообщение без кнопок', $jobData->queryParams->message);
        $this->assertNull($jobData->queryParams->keyboard);
    }
}
