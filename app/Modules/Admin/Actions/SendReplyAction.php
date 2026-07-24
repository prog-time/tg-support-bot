<?php

namespace App\Modules\Admin\Actions;

use App\Models\BotUser;
use App\Models\Message;
use App\Models\User;
use App\Modules\Admin\Jobs\MirrorAdminReplyToGroupJob;
use App\Modules\Admin\Jobs\SendAdminDocumentJob;
use App\Modules\Admin\Services\ChannelStatusService;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Max\Actions\UploadFileMax;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Telegram\Jobs\TopicCreateJob;
use App\Modules\Vk\Actions\GetMessagesUploadServerVk;
use App\Modules\Vk\Actions\SaveFileVk;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendReplyAction
{
    /**
     * Send a manager reply to the user via the appropriate platform.
     *
     * In addition to delivering the reply to the user and saving the messages row,
     * when the Telegram supergroup is configured (telegram.token + telegram.group_id)
     * the reply is mirrored to the user's forum topic with the prefix
     * «Ответ из админки: ». The mirror is text-only; file mirroring is deferred.
     * The mirror DOES NOT create a second messages row and DOES NOT re-deliver to the user.
     *
     * @param BotUser           $botUser Target user
     * @param string            $text    Message text (may be empty when file is provided)
     * @param UploadedFile|null $file    Optional file attachment
     * @param User|null         $author  Operator sending the reply (null for AI/telegram-group paths)
     *
     * @return void
     */
    public static function execute(BotUser $botUser, string $text, ?UploadedFile $file = null, ?User $author = null): void
    {
        // A new reply re-opens a previously closed conversation.
        if ($botUser->isClosed()) {
            $botUser->update(['is_closed' => false, 'closed_at' => null]);
        }

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $text ?: null,
            'sender_user_id' => $author?->id,
            'sender_name' => $author?->name,
        ]);

        match (true) {
            $botUser->platform === 'telegram' => self::sendTelegramReply($botUser, $text, $file, $message),
            $botUser->platform === 'vk' => self::sendVkReply($botUser, $text, $file, $message),
            $botUser->platform === 'max' => self::sendMaxReply($botUser, $text, $file, $message),
            $botUser->platform === 'avito' => self::sendAvitoReply($botUser, $text, $file),
            $botUser->platform === 'email' => self::sendEmailReply($botUser, $text, $file, $message),
            default => self::sendExternalReply($botUser, $text),
        };

        self::maybeMirrorToGroup($botUser, $text, $file);
    }

    /**
     * Mirror the admin-panel reply to the Telegram supergroup forum topic.
     *
     * Only fires when the Telegram channel integration is fully configured
     * (telegram.token + telegram.group_id set in settings). Text-only mirror;
     * file attachment mirroring is out of scope for now.
     *
     * If the user's forum topic does not yet exist, TopicCreateJob is dispatched
     * first and MirrorAdminReplyToGroupJob will retry until topic_id is available.
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     *
     * @return void
     */
    private static function maybeMirrorToGroup(BotUser $botUser, string $text, ?UploadedFile $file): void
    {
        $groupId = (string) app(SettingsService::class)->get('telegram.group_id');
        if (!app(ChannelStatusService::class)->telegram()['connected'] || $groupId === '') {
            return;
        }

        // Determine mirror text — file-only replies get a placeholder. The
        // «Ответ из админки:» label + newline is added by the job's prefix.
        $mirrorText = $text !== ''
            ? $text
            : '[вложение]';

        // Ensure the forum topic exists before mirroring.
        if (empty($botUser->topic_id)) {
            TopicCreateJob::dispatch($botUser->id);
        }

        MirrorAdminReplyToGroupJob::dispatch($botUser->id, $mirrorText);
    }

    /**
     * Send reply via MAX (text or file).
     *
     * Files are uploaded to MAX's CDN to obtain an attachment token, then sent
     * via the matching method (image → sendImage, audio → sendAudio, anything
     * else → sendFile). If the upload fails, the text is still delivered (when
     * present) so the reply is not silently lost. The Message row is already
     * created by execute(), so the "simple" send job is used (no second save).
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     * @param Message           $message
     *
     * @return void
     */
    /**
     * Deliver a manager's reply to an Avito user.
     *
     * Avito is text-only in this iteration: an attached file cannot be
     * delivered, so it is logged and skipped rather than silently dropped. When
     * there is no text either, nothing is sent.
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     *
     * @return void
     */
    private static function sendAvitoReply(BotUser $botUser, string $text, ?UploadedFile $file): void
    {
        if ($file !== null) {
            Log::channel('app')->warning('SendReplyAction: Avito does not support file replies, attachment skipped', [
                'bot_user_id' => $botUser->id,
            ]);
        }

        if ($text === '') {
            return;
        }

        SendAvitoSimpleMessageJob::dispatch(
            AvitoTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $botUser->chat_id,
                'text' => $text,
            ])
        );
    }

    /**
     * Deliver a manager's reply to an email user, as a plain SMTP attachment
     * when a file is present.
     *
     * The attachment is a single file per reply (SMTP has no per-item preview
     * like Telegram/VK, so there's no reason to special-case images). The
     * `UploadedFile` cannot survive queue serialization, so it's copied to a
     * stable path first (mirrors sendTelegramReply()) and the copied file is
     * also recorded on the local Message via recordOutgoingAttachment() so the
     * admin thread can render/download it — email has no provider-native
     * re-fetchable file id to fall back on, unlike Telegram's file_id.
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     * @param Message           $message
     *
     * @return void
     */
    private static function sendEmailReply(BotUser $botUser, string $text, ?UploadedFile $file, Message $message): void
    {
        if ($text === '' && $file === null) {
            return;
        }

        $attachment = $file !== null ? self::copyEmailAttachment($file) : null;

        if ($file !== null && $attachment !== null) {
            self::recordOutgoingAttachment($message, $file);
        }

        $headers = app(EmailThreadStore::class)->replyHeaders($botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => $text,
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
                'attachmentPath' => $attachment['path'] ?? null,
                'attachmentName' => $attachment['name'] ?? null,
                'attachmentMime' => $attachment['mime'] ?? null,
            ])
        );
    }

    /**
     * Copy a manager-reply file to a queue-safe path for the Email attachment.
     *
     * @param UploadedFile $file
     *
     * @return array{path: string, name: string, mime: string}|null null on any read failure.
     */
    private static function copyEmailAttachment(UploadedFile $file): ?array
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return null;
        }

        $ext = $file->getClientOriginalExtension();
        $dir = storage_path('app/temp_attachments');
        $destPath = $dir . '/' . Str::uuid() . ($ext ? '.' . $ext : '');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!copy($realPath, $destPath)) {
            return null;
        }

        return [
            'path' => $destPath,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
        ];
    }

    private static function sendMaxReply(BotUser $botUser, string $text, ?UploadedFile $file, Message $message): void
    {
        if ($file !== null) {
            $token = self::uploadMaxFile($file);

            if ($token !== null) {
                $mime = $file->getMimeType() ?? 'application/octet-stream';
                $method = match (true) {
                    str_starts_with($mime, 'image/') => 'sendImage',
                    str_starts_with($mime, 'audio/') => 'sendAudio',
                    default => 'sendFile',
                };

                // Record the attachment on the local Message so the admin thread
                // can render it (MAX has no re-fetchable file id; we serve our own
                // stored copy via its public URL).
                self::recordOutgoingAttachment($message, $file);

                SendMaxSimpleMessageJob::dispatch(
                    MaxTextMessageDto::from([
                        'methodQuery' => $method,
                        'user_id' => (int) $botUser->chat_id,
                        'text' => $text,
                        'file_token' => $token,
                    ])
                );

                return;
            }

            // Upload failed: with no text there is nothing left to deliver.
            if ($text === '') {
                Log::channel('app')->error('SendReplyAction: MAX file upload failed, nothing to send', [
                    'bot_user_id' => $botUser->id,
                ]);

                return;
            }
        }

        SendMaxSimpleMessageJob::dispatch(
            MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => (int) $botUser->chat_id,
                'text' => $text,
            ])
        );
    }

    /**
     * Upload a manager-reply file to MAX and return the attachment token.
     *
     * Maps the MIME type to a MAX upload type (image / audio / file) and
     * delegates to UploadFileMax. Returns null on any read/upload failure.
     *
     * @param UploadedFile $file
     *
     * @return string|null
     */
    private static function uploadMaxFile(UploadedFile $file): ?string
    {
        $realPath = $file->getRealPath();

        if ($realPath === false) {
            return null;
        }

        $contents = @file_get_contents($realPath);

        if ($contents === false) {
            return null;
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $type = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };

        return app(UploadFileMax::class)->uploadContents($contents, $file->getClientOriginalName(), $type);
    }

    /**
     * Persist an outgoing reply file and record it on the Message.
     *
     * Stores the file on the private `local` disk and saves a MessageAttachment
     * whose `file_id` is the storage path (`chat-attachments/…`). The chat thread
     * serves it through the auth-gated `admin.chat-attachment` route — no public
     * disk / symlink / web-`/storage` dependency. `file_type` is mapped so images
     * get an inline preview (`photo`) and everything else a download link.
     * Best-effort: failures are logged, the message is still sent.
     *
     * @param Message      $message
     * @param UploadedFile $file
     *
     * @return void
     */
    private static function recordOutgoingAttachment(Message $message, UploadedFile $file): void
    {
        try {
            $path = $file->store('chat-attachments', 'local');

            if (!is_string($path) || $path === '') {
                return;
            }

            $mime = $file->getMimeType() ?? '';
            $type = match (true) {
                str_starts_with($mime, 'image/') => 'photo',
                str_starts_with($mime, 'video/') => 'video_note',
                str_starts_with($mime, 'audio/') => 'audio_message',
                default => 'document',
            };

            $message->attachments()->create([
                'file_id' => $path,
                'file_type' => $type,
                'file_name' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->error('SendReplyAction: failed to record outgoing attachment | ' . $e->getMessage());
        }
    }

    /**
     * Send reply via Telegram (text or document).
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     * @param Message           $message
     *
     * @return void
     */
    private static function sendTelegramReply(BotUser $botUser, string $text, ?UploadedFile $file, Message $message): void
    {
        if ($file !== null) {
            // UploadedFile cannot be serialized by the queue.
            // Copy the Livewire temp file to a controlled directory and pass the path.
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                return;
            }

            $ext = $file->getClientOriginalExtension();
            $dir = storage_path('app/temp_attachments');
            $destPath = $dir . '/' . Str::uuid() . ($ext ? '.' . $ext : '');

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!copy($realPath, $destPath)) {
                return;
            }

            SendAdminDocumentJob::dispatch(
                dbMessageId:  $message->id,
                chatId:       (int) $botUser->chat_id,
                filePath:     $destPath,
                caption:      $text ?: null,
                originalName: $file->getClientOriginalName(),
                mimeType:     $file->getMimeType() ?? 'application/octet-stream',
            );

            return;
        }

        SendTelegramSimpleQueryJob::dispatch(
            TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $botUser->chat_id,
                'text' => $text,
            ])
        );
    }

    /**
     * Send reply via VK (text or document).
     *
     * On a successful document upload the file is also recorded on the local
     * Message (via recordOutgoingAttachment) so the admin chat workspace can
     * render it — without this the bubble shows only the «Вложение» placeholder.
     *
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     * @param Message           $message
     *
     * @return void
     */
    private static function sendVkReply(BotUser $botUser, string $text, ?UploadedFile $file, Message $message): void
    {
        $attachment = $file !== null ? self::uploadVkDocument($botUser, $file) : null;

        // Record the attachment locally so the admin thread can show it (VK doc
        // URLs are not re-served here; we serve our own stored copy).
        if ($file !== null && $attachment !== null) {
            self::recordOutgoingAttachment($message, $file);
        }

        SendVkSimpleMessageJob::dispatch(
            VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $botUser->chat_id,
                'message' => $text,
                'attachment' => $attachment,
            ])
        );
    }

    /**
     * Upload a document to VK and return the attachment string (e.g. "doc123_456").
     *
     * Reuses the proven VK upload pipeline (GetMessagesUploadServerVk /
     * SaveFileVk) and uploads the raw file contents — the same mechanism used
     * for Telegram→VK media forwarding. Returns null on any error so the message
     * is still sent without a file; every failure is logged (token excluded) so
     * the previously-silent failures are now diagnosable.
     *
     * @param BotUser      $botUser
     * @param UploadedFile $file
     *
     * @return string|null
     */
    private static function uploadVkDocument(BotUser $botUser, UploadedFile $file): ?string
    {
        // Step 1: get the messages upload server for documents.
        $serverDto = app(GetMessagesUploadServerVk::class)->execute((int) $botUser->chat_id, 'docs');

        $uploadUrl = is_array($serverDto->response) ? ($serverDto->response['upload_url'] ?? null) : null;
        if (empty($uploadUrl)) {
            Log::channel('app')->error('SendReplyAction: VK upload server not obtained', [
                'bot_user_id' => $botUser->id,
                'error' => $serverDto->error_message,
            ]);

            return null;
        }

        // Step 2: upload the file to VK's server (field name "file").
        $realPath = $file->getRealPath();
        $fileHandle = $realPath !== false ? @fopen($realPath, 'rb') : false;
        if ($fileHandle === false) {
            Log::channel('app')->error('SendReplyAction: VK file read failed', [
                'bot_user_id' => $botUser->id,
            ]);

            return null;
        }

        try {
            $uploadResponse = Http::attach('file', $fileHandle, $file->getClientOriginalName())
                ->post($uploadUrl);
        } catch (\Throwable $e) {
            Log::channel('app')->error('SendReplyAction: VK file upload request failed | ' . $e->getMessage(), [
                'bot_user_id' => $botUser->id,
            ]);

            return null;
        }

        $uploadData = $uploadResponse->json();
        if (!is_array($uploadData) || empty($uploadData['file'])) {
            Log::channel('app')->error('SendReplyAction: VK upload returned no file token', [
                'bot_user_id' => $botUser->id,
                'status' => $uploadResponse->status(),
            ]);

            return null;
        }

        // Step 3: persist the document in VK.
        $saveDto = app(SaveFileVk::class)->execute('docs', $uploadData);

        $doc = is_array($saveDto->response) ? ($saveDto->response['doc'] ?? null) : null;
        if (!is_array($doc) || empty($doc['owner_id']) || empty($doc['id'])) {
            Log::channel('app')->error('SendReplyAction: VK docs.save failed', [
                'bot_user_id' => $botUser->id,
                'error' => $saveDto->error_message,
            ]);

            return null;
        }

        return 'doc' . $doc['owner_id'] . '_' . $doc['id'];
    }

    /**
     * Send reply to an external source via webhook.
     *
     * @param BotUser $botUser
     * @param string  $text
     *
     * @return void
     */
    private static function sendExternalReply(BotUser $botUser, string $text): void
    {
        $botUser->load('externalUser.externalSource');
        $webhookUrl = $botUser->externalUser?->externalSource?->webhook_url;

        if (empty($webhookUrl)) {
            return;
        }

        SendWebhookMessage::dispatch($webhookUrl, [
            'type_query' => 'send_message',
            'externalId' => $botUser->externalUser->external_id,
            'message' => [
                'content_type' => 'text',
                'message_type' => 'outgoing',
                'text' => $text,
                'date' => date('d.m.Y H:i'),
            ],
        ]);
    }
}
