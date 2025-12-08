<?php

namespace Tests\Unit\Services\Tg;

use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Services\Tg\TgMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDtoMock;
use Tests\TestCase;

class TgMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->botUser = BotUser::getUserByChatId(config('testing.tg_private.chat_id'), 'telegram');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        $payload = TelegramUpdateDtoMock::getDtoParams();
        $payload['message']['message_thread_id'] = $this->botUser->topic_id;
        $this->basicPayload = $payload;

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => time(),
                    'from' => [
                        'id' => time(),
                        'is_bot' => true,
                        'first_name' => 'Prog-Time |Администратор сайта',
                        'username' => 'prog_time_bot',
                    ],
                    'chat' => [
                        'id' => config('testing.tg_private.chat_id'),
                        'first_name' => config('testing.tg_private.first_name'),
                        'last_name' => config('testing.tg_private.last_name'),
                        'username' => config('testing.tg_private.username'),
                        'type' => 'private',
                    ],
                    'date' => time(),
                    'text' => 'Тестовое сообщение',
                ],
            ]),
        ]);
    }

    public function test_send_text_message(): void
    {
        $dto = TelegramUpdateDtoMock::getDto($this->basicPayload);

        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_photo(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            [
                'file_id' => config('testing.tg_file.photo'),
                'file_unique_id' => 'AQAD854DoEp9',
                'file_size' => 59609,
                'width' => 684,
                'height' => 777,
            ],
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendPhoto', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['document'] = [
            'file_id' => config('testing.tg_file.document'),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendDocument', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_sticker(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['sticker'] = [
            'file_id' => config('testing.tg_file.sticker'),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendSticker', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_location(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['location'] = [
            'latitude' => 55.728387,
            'longitude' => 37.611953,
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendLocation', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_video_note(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['video_note'] = [
            'file_id' => config('testing.tg_file.video_note'),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendVideoNote', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_voice(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['voice'] = [
            'file_id' => config('testing.tg_file.voice'),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendVoice', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }

    public function test_send_contact(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['contact'] = [
            'phone_number' => '79999999999',
            'first_name' => 'Тестовый',
            'last_name' => 'Тест',
            'user_id' => config('testing.tg_private.chat_id'),
        ];

        $dto = TelegramUpdateDtoMock::getDto($payload);
        (new TgMessageService($dto))->handleUpdate();

        /** @phpstan-ignore-next-line */
        $pushed = Queue::pushedJobs()[SendTelegramMessageJob::class] ?? [];
        $this->assertCount(1, $pushed);

        // Проверяем первую джобу (создание)
        $firstJob = $pushed[0]['job'];
        $this->assertEquals('sendMessage', $firstJob->queryParams->methodQuery);
        $this->assertEquals($this->botUser->id, $firstJob->botUserId);
    }
}
