<?php

namespace Tests\Unit\Modules\Email\Services;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Modules\Ai\Jobs\SendAiDraftJob;
use App\Modules\Ai\Jobs\SendAiReplyJob;
use App\Modules\Ai\Services\ShouldAiReply;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Modules\Email\Services\EmailMessageService;
use App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use Tests\Traits\SeedsSettings;

/**
 * Covers App\Modules\Email\Services\EmailMessageService::handleUpdate().
 *
 * Two mutually exclusive branches (mirrors AvitoMessageServiceTest):
 *   - group-ON  → forwards via SendEmailTelegramMessageJob, no direct Message row
 *   - group-OFF → persists a Message row directly, then maybeDispatchAi()
 *
 * TestCase::setUp() seeds a default telegram.group_id, so group-ON is the
 * baseline state; group-OFF tests must call clearGroupId() first.
 */
class EmailMessageServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsSettings;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function clearGroupId(): void
    {
        app(\App\Services\Settings\SettingsService::class)->forget('telegram.group_id');
    }

    private function makeDto(
        string $chatId = 'user@example.com',
        string $senderName = 'User Name',
        ?string $text = 'Здравствуйте',
        string $subject = 'Нужна помощь',
        array $attachments = [],
    ): EmailUpdateDto {
        return new EmailUpdateDto(
            chatId: $chatId,
            senderName: $senderName,
            subject: $subject,
            text: $text,
            messageId: 'msg-1@example.com',
            references: null,
            attachments: $attachments,
        );
    }

    /**
     * A fake attachment shaped like EmailImapClient::extractAttachments()'s
     * output — a real temp file at `path` (as the queued job would receive)
     * and a `storedPath` on the faked `local` disk.
     *
     * @return array{path: string, storedPath: string, name: string, mime: string}
     */
    private function makeAttachment(string $name = 'photo.jpg', string $mime = 'image/jpeg'): array
    {
        $dir = storage_path('app/temp_attachments');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . uniqid('test_', true) . '.jpg';
        file_put_contents($path, 'fake-bytes');

        $storedPath = 'chat-attachments/' . uniqid('test_', true) . '.jpg';
        Storage::disk('local')->put($storedPath, 'fake-bytes');

        return [
            'path' => $path,
            'storedPath' => $storedPath,
            'name' => $name,
            'mime' => $mime,
        ];
    }

    // ── group-ON ─────────────────────────────────────────────────────────────

    public function test_group_on_dispatches_email_telegram_job(): void
    {
        $dto = $this->makeDto();
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job) use ($botUser, $dto): bool {
            // Not the same instance as $dto (identity) — sendMessage() passes
            // a queue-safe copy with providerRef stripped, see
            // test_group_on_strips_provider_ref_before_dispatch_to_avoid_queue_crash().
            return $job->botUserId === $botUser->id
                && $job->updateDto->chatId === $dto->chatId
                && $job->updateDto->text === $dto->text
                && $job->queryParams->methodQuery === 'sendMessage'
                && $job->queryParams->text === "Тема: Нужна помощь\n\nЗдравствуйте"
                && $job->queryParams->message_thread_id === $botUser->topic_id;
        });
    }

    public function test_group_on_forwards_image_attachment_as_send_photo(): void
    {
        Storage::fake('local');

        $attachment = $this->makeAttachment('photo.jpg', 'image/jpeg');
        $dto = $this->makeDto(chatId: 'photo@example.com', text: 'Смотрите фото', attachments: [$attachment]);
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job) use ($botUser, $attachment): bool {
            return $job->botUserId === $botUser->id
                && $job->queryParams->methodQuery === 'sendPhoto'
                && $job->queryParams->uploaded_file_path === $attachment['path']
                && $job->queryParams->caption === "Тема: Нужна помощь\n\nСмотрите фото"
                && $job->queryParams->text === null;
        });
    }

    public function test_group_on_forwards_non_image_attachment_as_send_document(): void
    {
        Storage::fake('local');

        $attachment = $this->makeAttachment('report.pdf', 'application/pdf');
        $dto = $this->makeDto(chatId: 'doc@example.com', text: 'Отчёт во вложении', attachments: [$attachment]);
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job) use ($attachment): bool {
            return $job->queryParams->methodQuery === 'sendDocument'
                && $job->queryParams->uploaded_file_path === $attachment['path'];
        });
    }

    public function test_group_on_strips_provider_ref_before_dispatch_to_avoid_queue_crash(): void
    {
        // Regression: EmailUpdateDto::$providerRef holds the raw IMAP message
        // object. Serializing the whole job for the queue payload (even under
        // QUEUE_CONNECTION=sync — Illuminate\Queue\Queue::createPayload() still
        // runs) breaks on an email with an attachment: the serialized bytes
        // aren't valid UTF-8, json_encode() fails, dispatch() throws, and the
        // source email is never marked seen — retried forever on every poll.
        $dto = $this->makeDto();
        $dtoWithProviderRef = new EmailUpdateDto(
            chatId: $dto->chatId,
            senderName: $dto->senderName,
            subject: $dto->subject,
            text: $dto->text,
            messageId: $dto->messageId,
            references: $dto->references,
            providerRef: new \stdClass(),
        );
        BotUser::getUserByChatId($dtoWithProviderRef->chatId, 'email');

        (new EmailMessageService($dtoWithProviderRef))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job): bool {
            return $job->updateDto->providerRef === null;
        });
    }

    public function test_group_on_escapes_angle_brackets_in_telegram_text_but_not_ai_text(): void
    {
        // Regression test: a mail client's own reply-quote header ("...От
        // Служба поддержки <support@example.com>: ...") routinely contains a
        // bare '<address' in the plain-text body. Sent unescaped with
        // Telegram's parse_mode=html, that 400s with "can't parse entities" —
        // silently, since the job's own failure handling doesn't throw — and
        // the reply never reaches Telegram. AI should still see the natural,
        // unescaped text.
        $dto = $this->makeDto(
            chatId: 'quoted-reply@example.com',
            text: 'см. <support@example.com> для деталей & подробностей',
        );
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->queryParams->text === "Тема: Нужна помощь\n\nсм. &lt;support@example.com&gt; для деталей &amp; подробностей";
        });
    }

    public function test_group_on_does_not_persist_message_directly(): void
    {
        $dto = $this->makeDto();
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    public function test_group_on_is_handled_successfully(): void
    {
        $dto = $this->makeDto();
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
    }

    // ── group-OFF ────────────────────────────────────────────────────────────

    public function test_group_off_persists_incoming_message(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'group-off@example.com', text: 'Привет');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'email',
            'message_type' => 'incoming',
            'from_id' => 0,
            'to_id' => 0,
            'text' => "Тема: Нужна помощь\n\nПривет",
        ]);

        $this->assertSame(1, Message::where('bot_user_id', $botUser->id)->count());
    }

    public function test_group_off_persists_body_only_when_subject_is_blank(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'no-subject@example.com', text: 'Привет', subject: '');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $this->assertDatabaseHas('messages', [
            'bot_user_id' => $botUser->id,
            'platform' => 'email',
            'text' => 'Привет',
        ]);
    }

    public function test_group_off_records_local_attachment_for_admin_workspace(): void
    {
        $this->clearGroupId();
        Storage::fake('local');

        $attachment = $this->makeAttachment('photo.jpg', 'image/jpeg');
        $dto = $this->makeDto(chatId: 'group-off-photo@example.com', text: 'Фото во вложении', attachments: [$attachment]);
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $message = Message::where('bot_user_id', $botUser->id)->first();
        $this->assertNotNull($message);

        $recorded = MessageAttachment::where('message_id', $message->id)->first();
        $this->assertNotNull($recorded);
        $this->assertSame('photo', $recorded->file_type);
        $this->assertSame($attachment['storedPath'], $recorded->file_id);
        $this->assertSame('photo.jpg', $recorded->file_name);
    }

    public function test_group_off_without_attachment_records_no_attachment(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'group-off-plain@example.com', text: 'Просто текст');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $message = Message::where('bot_user_id', $botUser->id)->first();
        $this->assertSame(0, MessageAttachment::where('message_id', $message->id)->count());
    }

    public function test_group_off_does_not_dispatch_email_telegram_job(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'group-off-2@example.com');
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
    }

    // ── Ignored inputs ───────────────────────────────────────────────────────

    public function test_empty_text_does_nothing_but_is_still_handled(): void
    {
        $dto = $this->makeDto(chatId: 'empty-text@example.com', text: '');
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    public function test_empty_text_with_attachment_still_forwards(): void
    {
        // Regression: an attachment-only email (no typed body) must not be
        // dropped by the "nothing to route" empty-text guard.
        Storage::fake('local');

        $attachment = $this->makeAttachment('photo.jpg', 'image/jpeg');
        $dto = $this->makeDto(chatId: 'empty-text-with-file@example.com', text: '', attachments: [$attachment]);
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
        Queue::assertPushed(SendEmailTelegramMessageJob::class, function (SendEmailTelegramMessageJob $job): bool {
            return $job->queryParams->methodQuery === 'sendPhoto';
        });
    }

    public function test_null_text_does_nothing_but_is_still_handled(): void
    {
        $dto = $this->makeDto(chatId: 'null-text@example.com', text: null);
        BotUser::getUserByChatId($dto->chatId, 'email');

        $service = new EmailMessageService($dto);
        $service->handleUpdate();

        $this->assertTrue($service->handledSuccessfully());
        Queue::assertNotPushed(SendEmailTelegramMessageJob::class);
        $this->assertSame(0, Message::where('platform', 'email')->count());
    }

    // ── captureProfile ───────────────────────────────────────────────────────

    public function test_captures_display_name_when_empty(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'name-capture@example.com', senderName: 'Ivan Petrov');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');
        $this->assertNull($botUser->display_name);

        (new EmailMessageService($dto))->handleUpdate();

        $botUser->refresh();
        $this->assertSame('Ivan Petrov', $botUser->display_name);
    }

    public function test_does_not_overwrite_existing_display_name(): void
    {
        $this->clearGroupId();

        $dto = $this->makeDto(chatId: 'name-keep@example.com', senderName: 'Mail Client Name');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');
        $botUser->update(['display_name' => 'Operator Set Name']);

        (new EmailMessageService($dto))->handleUpdate();

        $botUser->refresh();
        $this->assertSame('Operator Set Name', $botUser->display_name);
    }

    // ── No double-write invariant ───────────────────────────────────────────

    public function test_never_creates_two_message_rows_regardless_of_branch(): void
    {
        $dtoOn = $this->makeDto(chatId: 'invariant-on@example.com');
        $botUserOn = BotUser::getUserByChatId($dtoOn->chatId, 'email');
        (new EmailMessageService($dtoOn))->handleUpdate();

        $this->assertSame(0, Message::where('bot_user_id', $botUserOn->id)->count());

        $this->clearGroupId();
        $dtoOff = $this->makeDto(chatId: 'invariant-off@example.com');
        $botUserOff = BotUser::getUserByChatId($dtoOff->chatId, 'email');
        (new EmailMessageService($dtoOff))->handleUpdate();

        $this->assertSame(1, Message::where('bot_user_id', $botUserOff->id)->count());
    }

    // ── Thread store ─────────────────────────────────────────────────────────

    public function test_remembers_thread_for_reply_headers(): void
    {
        $this->clearGroupId();
        Cache::flush();

        $dto = $this->makeDto(chatId: 'thread@example.com', subject: 'Order #42');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        $thread = app(\App\Modules\Email\Services\EmailThreadStore::class)->get($botUser->id);
        $this->assertNotNull($thread);
        $this->assertSame('msg-1@example.com', $thread['message_id']);
        $this->assertSame('Order #42', $thread['subject']);
    }

    // ── AI dispatch ──────────────────────────────────────────────────────────

    public function test_group_off_dispatches_ai_reply_job_when_auto_reply_enabled(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', true);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'ai-auto@example.com', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAiReplyJob::class, function (SendAiReplyJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->updateDto === null
                && $job->userMessage === "Тема: Нужна помощь\n\nНужна помощь";
        });
        Queue::assertNotPushed(SendAiDraftJob::class);
    }

    public function test_group_off_dispatches_ai_draft_job_when_auto_reply_disabled(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', false);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(true);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'ai-draft@example.com', text: 'Нужна помощь');
        $botUser = BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertPushed(SendAiDraftJob::class, function (SendAiDraftJob $job) use ($botUser): bool {
            return $job->botUserId === $botUser->id
                && $job->updateDto === null
                && $job->userMessage === "Тема: Нужна помощь\n\nНужна помощь";
        });
        Queue::assertNotPushed(SendAiReplyJob::class);
    }

    public function test_ai_not_dispatched_when_should_ai_reply_denies(): void
    {
        $this->clearGroupId();
        $this->seedSetting('ai.auto_reply', true);

        $mock = Mockery::mock(ShouldAiReply::class);
        $mock->shouldReceive('shouldGenerateForBotUserText')->once()->andReturn(false);
        $this->app->instance(ShouldAiReply::class, $mock);

        $dto = $this->makeDto(chatId: 'ai-denied@example.com');
        BotUser::getUserByChatId($dto->chatId, 'email');

        (new EmailMessageService($dto))->handleUpdate();

        Queue::assertNotPushed(SendAiReplyJob::class);
        Queue::assertNotPushed(SendAiDraftJob::class);
    }
}
