<?php

namespace Tests\Unit\Services\TgVk;

use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

        $this->botUser = BotUser::getUserByChatId(config('testing.vk_private.chat_id'), 'vk');
        $this->botUser->topic_id = 123;
        $this->botUser->save();

        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams();
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
        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            ['file_id' => config('testing.tg_file.photo')],
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

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['document'] = [
            'file_id' => config('testing.tg_file.document'),
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
            'file_id' => config('testing.tg_file.sticker'),
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
            'user_id' => config('testing.tg_private.chat_id'),
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
