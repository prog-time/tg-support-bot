<?php

namespace Tests\Unit\Modules\Telegram\Services\TgEmail;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Telegram\Services\TgEmail\TgEmailMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Mocks\Tg\TelegramUpdateDto_VKMock;
use Tests\TestCase;

/**
 * A manager's reply typed in the Telegram supergroup topic must reach the Email
 * user. The controller routes by platform, and before this handler existed the
 * 'email' case fell into the External-Sources default arm — which has no webhook
 * for email — so the reply was silently dropped while incoming mail still worked.
 */
class TgEmailMessageServiceTest extends TestCase
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

        $this->botUser = BotUser::getUserByChatId('client@example.com', 'email');
        $this->botUser->topic_id = 321;
        $this->botUser->save();

        $this->basicPayload = TelegramUpdateDto_VKMock::getDtoParams($this->botUser);
    }

    public function test_group_topic_reply_is_sent_as_email(): void
    {
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgEmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailMessageJob::class, function ($job) use ($dto) {
            return $job->queryParams->to === 'client@example.com'
                && $job->queryParams->text === $dto->text;
        });
    }

    public function test_group_topic_reply_records_the_outgoing_message(): void
    {
        // The send job only sends — this handler must record the row itself, or
        // the reply never appears in /admin/chats and breaks BR-002.
        $dto = TelegramUpdateDto_VKMock::getDto($this->basicPayload);

        (new TgEmailMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'email',
            'message_type' => 'outgoing',
            'text' => $dto->text,
        ]);
    }

    public function test_empty_reply_sends_nothing(): void
    {
        $payload = $this->basicPayload;
        $payload['message']['text'] = '';

        (new TgEmailMessageService(TelegramUpdateDto_VKMock::getDto($payload)))->handleUpdate();

        Queue::assertNotPushed(SendEmailMessageJob::class);
        $this->assertDatabaseCount('messages', 0);
    }

    // ── Photo/document reply → SMTP attachment ──────────────────────────────

    private function fakeTelegramFileDownload(string $fileId, string $filePath, string $body, string $contentType): void
    {
        Http::fake([
            "api.telegram.org/bot{$this->botToken}/getFile*" => Http::response([
                'result' => ['file_path' => $filePath],
            ]),
            "api.telegram.org/file/bot{$this->botToken}/{$filePath}" => Http::response($body, 200, [
                'Content-Type' => $contentType,
            ]),
        ]);
    }

    public function test_photo_reply_is_forwarded_as_email_attachment(): void
    {
        Storage::fake('local');
        $this->fakeTelegramFileDownload('photo_file_1', 'photos/file_1.jpg', 'fake-jpeg-bytes', 'image/jpeg');

        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['caption'] = 'Ответ с фото';
        $payload['message']['photo'] = [
            ['file_id' => 'photo_file_1', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90],
        ];

        (new TgEmailMessageService(TelegramUpdateDto_VKMock::getDto($payload)))->handleUpdate();

        Queue::assertPushed(SendEmailMessageJob::class, function ($job): bool {
            return $job->queryParams->to === 'client@example.com'
                && $job->queryParams->text === 'Ответ с фото'
                && $job->queryParams->attachmentMime === 'image/jpeg'
                && is_string($job->queryParams->attachmentPath)
                && is_file($job->queryParams->attachmentPath);
        });

        $attachment = MessageAttachment::where('file_type', 'photo')->first();
        $this->assertNotNull($attachment);
        $this->assertStringStartsWith('chat-attachments/', (string) $attachment->file_id);
        Storage::disk('local')->assertExists($attachment->file_id);

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $this->botUser->id,
            'platform' => 'email',
            'message_type' => 'outgoing',
            'text' => 'Ответ с фото',
        ]);
    }

    public function test_document_reply_keeps_original_filename(): void
    {
        Storage::fake('local');
        $this->fakeTelegramFileDownload('doc_file_1', 'documents/file_2.pdf', 'fake-pdf-bytes', 'application/pdf');

        $payload = $this->basicPayload;
        unset($payload['message']['text']);
        $payload['message']['document'] = [
            'file_id' => 'doc_file_1',
            'file_name' => 'report.pdf',
        ];

        (new TgEmailMessageService(TelegramUpdateDto_VKMock::getDto($payload)))->handleUpdate();

        Queue::assertPushed(SendEmailMessageJob::class, function ($job): bool {
            return $job->queryParams->attachmentName === 'report.pdf'
                && $job->queryParams->attachmentMime === 'application/pdf';
        });

        $attachment = MessageAttachment::where('file_name', 'report.pdf')->first();
        $this->assertNotNull($attachment);
        $this->assertSame('document', $attachment->file_type);
    }
}
