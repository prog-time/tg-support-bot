<?php

namespace Tests\Unit\Modules\Max\Services;

use App\Models\BotUser;
use App\Modules\Max\Services\MaxMessageService;
use App\Modules\Telegram\Jobs\SendMaxTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Max\MaxUpdateDtoMock;
use Tests\TestCase;

class MaxMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotUser $botUser;

    private array $basicPayload;

    private string $groupChatId;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->groupChatId = config('traffic_source.settings.telegram.group_id');

        $payload = MaxUpdateDtoMock::getDtoParams();
        $chatId = $payload['message']['sender']['user_id'];
        $this->botUser = BotUser::getUserByChatId($chatId, 'max');

        $this->basicPayload = $payload;
    }

    public function test_send_text_message(): void
    {
        $dto = MaxUpdateDtoMock::getDto($this->basicPayload);

        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendMessage' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }

    public function test_send_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = null;
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'file',
                'payload' => [
                    'url' => 'https://example.com/test.pdf',
                    'filename' => 'test.pdf',
                ],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) use ($dto) {
            return
                $job->botUserId === $this->botUser->id &&

                $job->queryParams->methodQuery == 'sendDocument' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&

                $job->updateDto === $dto;
        });
    }
}
