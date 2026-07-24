<?php

namespace App\Modules\Telegram\Services\TgEmail;

use App\Helpers\TelegramHelper;
use App\Models\Message;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use App\Modules\Telegram\Services\ActionService\Send\FromTgMessageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Delivers a manager's reply typed in the Telegram supergroup topic to the
 * Email user, mirroring {@see \App\Modules\Telegram\Services\TgMax\TgMaxMessageService}.
 *
 * Without this the switch in {@see \App\Modules\Telegram\Controllers\TelegramBotController}
 * routed `platform = 'email'` group replies into the External-Sources default
 * arm, which has no webhook for email — so the reply was silently dropped even
 * though incoming mail worked (issue #214 follow-up). A single photo/document
 * typed into the topic is forwarded as a real SMTP attachment (see
 * {@see self::sendFileReply()}) — mirrors `SendReplyAction::sendEmailReply()`,
 * the admin-panel counterpart of this same capability.
 */
class TgEmailMessageService extends FromTgMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            $text = $this->update->text ?? $this->update->caption ?? null;

            if (!empty($this->update->fileId) && $this->update->fileType === 'photo') {
                $this->sendPhoto();
            } elseif (!empty($this->update->fileId) && $this->update->fileType === 'document') {
                $this->sendDocument();
            } elseif (!empty($text)) {
                $this->sendMessage($text);
            }
        } catch (\Throwable $e) {
            Log::channel('app')->log(
                $e->getCode() === 1 ? 'warning' : 'error',
                $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()]
            );
        }
    }

    /**
     * @param string $text
     *
     * @return void
     */
    protected function sendMessage(string $text = ''): void
    {
        if ($this->botUser === null || $text === '') {
            return;
        }

        // The send job only sends; the /admin/chats path records the row via
        // SendReplyAction, so the group-topic path records it here to keep
        // BR-002 (every sent message stored) and to surface the reply in the
        // admin workspace.
        Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $text,
        ]);

        $headers = app(EmailThreadStore::class)->replyHeaders($this->botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $this->botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => $text,
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
            ])
        );
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendPhoto(): void
    {
        $this->sendFileReply('jpg');
    }

    /**
     * @return void
     *
     * @throws \Exception
     */
    protected function sendDocument(): void
    {
        $this->sendFileReply(null, $this->update->rawData['message']['document']['file_name'] ?? null);
    }

    /**
     * Download the manager's Telegram photo/document and forward it to the
     * Email user as a real SMTP attachment (one file per reply, mirrors
     * `SendReplyAction::sendEmailReply()`).
     *
     * Downloads once and writes two on-disk copies, matching
     * `EmailImapClient::extractAttachments()`'s reasoning for the reverse
     * direction: a queue-safe temp path that `EmailMailer`'s SMTP send reads,
     * and a permanent `local`-disk copy under `chat-attachments/` for the
     * admin workspace.
     *
     * @param string|null $fallbackExtension Used when neither the original filename nor the CDN URL has one (Telegram photos).
     * @param string|null $originalName      The document's original filename, if Telegram sent one.
     *
     * @return void
     *
     * @throws \Exception
     */
    private function sendFileReply(?string $fallbackExtension, ?string $originalName = null): void
    {
        if ($this->botUser === null) {
            return;
        }

        $telegramFileUrl = TelegramHelper::getFileTelegramPath((string) $this->update->fileId);
        if (empty($telegramFileUrl)) {
            throw new \Exception('Failed to get Telegram file URL', 1);
        }

        $response = Http::get($telegramFileUrl);
        if ($response->failed()) {
            throw new \Exception("Failed to download Telegram file: status={$response->status()}", 1);
        }

        $content = $response->body();
        $mime = $response->header('Content-Type') ?: 'application/octet-stream';

        $nameExtension = $originalName !== null ? pathinfo($originalName, PATHINFO_EXTENSION) : '';
        $urlExtension = pathinfo((string) parse_url($telegramFileUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        $extension = $nameExtension !== '' ? $nameExtension : ($urlExtension !== '' ? $urlExtension : (string) $fallbackExtension);

        $name = $originalName ?: (Str::uuid() . ($extension !== '' ? '.' . $extension : ''));

        $storedPath = 'chat-attachments/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        Storage::disk('local')->put($storedPath, $content);

        $dir = storage_path('app/temp_attachments');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tempPath = $dir . '/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        file_put_contents($tempPath, $content);

        $caption = $this->update->caption ?? '';

        // Mirrors sendMessage(): the send job only sends, so the group-topic
        // path records the outgoing row itself (BR-002, admin workspace).
        $message = Message::create([
            'bot_user_id' => $this->botUser->id,
            'platform' => $this->botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $caption !== '' ? $caption : null,
        ]);

        $message->attachments()->create([
            'file_id' => $storedPath,
            'file_type' => str_starts_with($mime, 'image/') ? 'photo' : 'document',
            'file_name' => $name,
        ]);

        $headers = app(EmailThreadStore::class)->replyHeaders($this->botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $this->botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => $caption,
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
                'attachmentPath' => $tempPath,
                'attachmentName' => $name,
                'attachmentMime' => $mime,
            ])
        );
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        //
    }
}
