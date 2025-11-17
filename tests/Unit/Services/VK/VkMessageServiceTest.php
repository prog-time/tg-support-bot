<?php

namespace Tests\Unit\Services\VK;

use App\Jobs\SendMessage\SendVkTelegramMessageJob;
use App\Models\BotUser;
use App\Services\VK\VkMessageService;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Vk\VkUpdateDtoMock;
use Tests\TestCase;

class VkMessageServiceTest extends TestCase
{
    private BotUser $botUser;

    private array $basicPayload;

    private string $groupChatId;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->groupChatId = config('traffic_source.settings.telegram.group_id');

        $this->botUser = BotUser::getUserByChatId(config('testing.vk_private.chat_id'), 'vk');

        $payload = VkUpdateDtoMock::getDtoParams();
        $this->basicPayload = $payload;
    }

    public function test_send_text_message(): void
    {
        $dto = VkUpdateDtoMock::getDto($this->basicPayload);

        (new VkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUser->id === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendMessage' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }

    public function test_send_photo(): void
    {
        $payload = $this->basicPayload;
        $payload['object']['message']['attachments'] = [
            [
                'type' => 'photo',
                'photo' => [
                    'id' => 457241856,
                    'owner_id' => 222232176,
                    'orig_photo' => [
                        'url' => 'https://example.com/photo.jpg',
                    ],
                ],
            ],
        ];

        $dto = VkUpdateDtoMock::getDto($payload);
        (new VkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUser->id === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendDocument' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['object']['message']['attachments'] = [
            [
                'type' => 'doc',
                'doc' => [
                    'id' => 12345,
                    'owner_id' => 222232176,
                    'title' => 'test.pdf',
                    'url' => 'https://example.com/photo.jpg',
                ],
            ],
        ];

        $dto = VkUpdateDtoMock::getDto($payload);
        (new VkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUser->id === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendDocument' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }

    public function test_send_location(): void
    {
        $payload = $this->basicPayload;
        $payload['object']['message']['geo'] = [
            'coordinates' => [
                'latitude' => 55.524442,
                'longitude' => 37.705064,
            ],
            'place' => [
                'city' => 'деревня Сапроново',
                'country' => 'Россия',
                'title' => 'деревня Сапроново, Россия',
            ],
            'type' => 'point',
        ];

        $dto = VkUpdateDtoMock::getDto($payload);
        (new VkMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendVkTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUser->id === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendMessage' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }
}
