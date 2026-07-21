<?php

namespace Tests\Unit\Modules\Max\Services;

use App\Jobs\EnrichBotUserProfileJob;
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

        $this->groupChatId = '-100000000000';

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
                $job->queryParams->methodQuery === 'sendMessage' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id &&
                $job->updateDto === $dto;
        });
    }

    // ── Profile capture (issue #205) ────────────────────────────────────────────

    public function test_incoming_message_stores_sender_name_and_username(): void
    {
        // MAX carries the sender's name and @username in the webhook, so the
        // conversation is labelled with the person rather than a bare id.
        $payload = $this->basicPayload;
        $payload['message']['sender']['name'] = 'Иван Петров';
        $payload['message']['sender']['username'] = 'ivan_p';

        (new MaxMessageService(MaxUpdateDtoMock::getDto($payload)))->handleUpdate();

        $this->botUser->refresh();
        $this->assertSame('Иван Петров', $this->botUser->display_name);
        $this->assertSame('ivan_p', $this->botUser->username);
    }

    public function test_profile_capture_does_not_overwrite_an_existing_name(): void
    {
        $this->botUser->update(['display_name' => 'Имя оператора']);

        $payload = $this->basicPayload;
        $payload['message']['sender']['name'] = 'Иван Петров';

        (new MaxMessageService(MaxUpdateDtoMock::getDto($payload)))->handleUpdate();

        $this->assertSame('Имя оператора', $this->botUser->fresh()->display_name);
    }

    public function test_send_photo_attachment(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = null;
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'image',
                'payload' => [
                    'url' => 'https://example.com/photo.jpg',
                ],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'sendPhoto' &&
                $job->queryParams->photo === 'https://example.com/photo.jpg' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id;
        });
    }

    public function test_send_photo_with_caption(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = 'Look at this!';
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'image',
                'payload' => [
                    'url' => 'https://example.com/photo.jpg',
                ],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return
                $job->queryParams->methodQuery === 'sendPhoto' &&
                $job->queryParams->caption === 'Look at this!';
        });
    }

    public function test_send_document_attachment(): void
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

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'sendDocument' &&
                $job->queryParams->document === 'https://example.com/test.pdf' &&
                $job->queryParams->chat_id == $this->groupChatId &&
                $job->queryParams->message_thread_id === $this->botUser->topic_id;
        });
    }

    public function test_send_voice_attachment(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = null;
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'audio',
                'payload' => [
                    'url' => 'https://example.com/voice.ogg',
                ],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'sendVoice' &&
                $job->queryParams->voice === 'https://example.com/voice.ogg' &&
                $job->queryParams->chat_id == $this->groupChatId;
        });
    }

    public function test_send_video_attachment_forwarded_as_document(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = null;
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'video',
                'payload' => [
                    'url' => 'https://example.com/video.mp4',
                ],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return
                $job->botUserId === $this->botUser->id &&
                $job->queryParams->methodQuery === 'sendDocument' &&
                $job->queryParams->document === 'https://example.com/video.mp4';
        });
    }

    public function test_multiple_attachments_dispatch_separate_jobs(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = 'Caption for first';
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'image',
                'payload' => ['url' => 'https://example.com/photo1.jpg'],
            ],
            [
                'type' => 'image',
                'payload' => ['url' => 'https://example.com/photo2.jpg'],
            ],
            [
                'type' => 'file',
                'payload' => ['url' => 'https://example.com/doc.pdf', 'filename' => 'doc.pdf'],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        // Three attachments → three jobs
        Queue::assertPushed(SendMaxTelegramMessageJob::class, 3);
    }

    public function test_caption_only_on_first_attachment_when_multiple(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = 'First caption only';
        $payload['message']['body']['attachments'] = [
            [
                'type' => 'image',
                'payload' => ['url' => 'https://example.com/photo1.jpg'],
            ],
            [
                'type' => 'image',
                'payload' => ['url' => 'https://example.com/photo2.jpg'],
            ],
        ];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendMaxTelegramMessageJob::class, 2);

        // First job should carry the caption
        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return $job->queryParams->photo === 'https://example.com/photo1.jpg'
                && $job->queryParams->caption === 'First caption only';
        });

        // Second job should have no caption
        Queue::assertPushed(SendMaxTelegramMessageJob::class, function ($job) {
            return $job->queryParams->photo === 'https://example.com/photo2.jpg'
                && $job->queryParams->caption === null;
        });
    }

    public function test_no_job_dispatched_when_no_text_and_no_attachments(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['body']['text'] = null;
        $payload['message']['body']['attachments'] = [];

        $dto = MaxUpdateDtoMock::getDto($payload);
        (new MaxMessageService($dto))->handleUpdate();

        // getUserByChatId() now always dispatches EnrichBotUserProfileJob (BR-011).
        // Assert the message-processing job was not dispatched (no text/attachment to forward).
        Queue::assertNotPushed(SendMaxTelegramMessageJob::class);
    }
}
