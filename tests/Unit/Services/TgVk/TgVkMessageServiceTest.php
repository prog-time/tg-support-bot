<?php

namespace Tests\Unit\Services\TgVk;

use App\Jobs\SendMessage\SendVkMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Services\TgVk\TgVkMessageService;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

class TgVkMessageServiceTest extends TestCase
{
    private BotUser $botUser;

    private array $basicPayload;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Message::truncate();

        $this->botUser = BotUser::getTelegramUserData(TelegramUpdateDto_VKMock::getDto());
        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams();
    }

    public function test_send_text_message(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgVkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'messages.send' &&
                $job->queryParams->peer_id === $this->botUser->chat_id &&
                $job->queryParams->message === $dto->text &&
                $job->updateDto === $dto;
        });
    }

    public function test_send_photo(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['photo'] = [
            ['file_id' => config('testing.tg_file.photo')],
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'messages.send' &&
                $job->queryParams->peer_id === $this->botUser->chat_id &&
                !empty($job->queryParams->attachment) &&
                $job->updateDto === $dto;
        });
    }

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['document'] = [
            'file_id' => config('testing.tg_file.document'),
        ];

        $dto = TelegramUpdateDto_VKMock::getDto($payload);

        (new TgVkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'messages.send' &&
                $job->queryParams->peer_id === $this->botUser->chat_id &&
                !empty($job->queryParams->attachment) &&
                $job->updateDto === $dto;
        });
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

        Queue::assertPushed(SendVkMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'messages.send' &&
                $job->queryParams->peer_id === $this->botUser->chat_id &&
                $job->queryParams->message === $dto->rawData['message']['sticker']['emoji'] &&
                $job->updateDto === $dto;
        });
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

        Queue::assertPushed(SendVkMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'messages.send' &&
                $job->queryParams->peer_id === $this->botUser->chat_id &&
                !empty($job->queryParams->message) &&
                $job->updateDto === $dto;
        });
    }
}
