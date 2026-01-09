<?php

namespace Tests\Unit\Services\TgVk;

use App\DTOs\Vk\VkAnswerDto;
use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkMessageService;
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

        $botToken = '123:ABC';
        config(['traffic_source.settings.telegram.token' => $botToken]);

        Http::fake([
            // getFile
            'https://api.telegram.org/bot123:ABC/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'test_file_id',
                    'file_path' => 'path/file.jpg',
                ],
            ], 200),

            // download file
            'https://api.telegram.org/file/bot123:ABC/*' => Http::response(
                'FAKE_BINARY_CONTENT',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);
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

        Http::fake([
            'https://api.telegram.org/bot*/getFile*' => Http::response([
                'ok' => true,
                'result' => [
                    'file_id' => 'test_file_id',
                    'file_path' => 'path/file.jpg',
                ],
            ], 200),
            'https://api.telegram.org/file/bot*/*' => Http::response('FAKE_BINARY_CONTENT', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $tgFileUrl = "https://api.telegram.org/file/bot123:ABC/{$fileName}";
        $vkUploadFileUrl = 'https://vk.com/stub_file/123456789_987654321';
        Mockery::mock('alias:App\Actions\Vk\GetMessagesUploadServerVk')
            ->shouldReceive('execute')
            ->with($this->botUser->chat_id, 'photos')
            ->andReturn(VkAnswerDto::fromData([
                'response' => [
                    'upload_url' => $vkUploadFileUrl,
                ],
            ]));

        $UploadFileVkResponse = [
            'server' => 123456,
            'file' => '{"file":"ABCD1234"}',
            'hash' => 'abcdef1234567890',
        ];
        Mockery::mock('alias:App\Actions\Vk\UploadFileVk')
            ->shouldReceive('execute')
            ->with($vkUploadFileUrl, $tgFileUrl, 'photo')
            ->andReturn($UploadFileVkResponse);

        Mockery::mock('alias:App\Actions\Vk\SaveFileVk')
            ->shouldReceive('execute')
            ->andReturn(
                VkAnswerDto::fromData([
                    'response' => [
                        [
                            'id' => 1,
                            'owner_id' => 1,
                        ],
                    ],
                ])
            );

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
}
