<?php

namespace App\Modules\Admin\Actions;

use App\Models\BotUser;
use App\Models\Message;
use App\Modules\Admin\Jobs\SendAdminDocumentJob;
use App\Modules\External\Jobs\SendWebhookMessage;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Modules\Vk\Api\VkMethods;
use App\Modules\Vk\DTOs\VkTextMessageDto;
use App\Modules\Vk\Jobs\SendVkSimpleMessageJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SendReplyAction
{
    /**
     * Send a manager reply to the user via the appropriate platform.
     *
     * @param BotUser           $botUser Target user
     * @param string            $text    Message text (may be empty when file is provided)
     * @param UploadedFile|null $file    Optional file attachment
     *
     * @return void
     */
    public static function execute(BotUser $botUser, string $text, ?UploadedFile $file = null): void
    {
        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => 'outgoing',
            'from_id' => 0,
            'to_id' => 0,
            'text' => $text ?: null,
        ]);

        match (true) {
            $botUser->platform === 'telegram' => self::sendTelegramReply($botUser, $text, $file, $message),
            $botUser->platform === 'vk' => self::sendVkReply($botUser, $text, $file),
            default => self::sendExternalReply($botUser, $text),
        };
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
     * @param BotUser           $botUser
     * @param string            $text
     * @param UploadedFile|null $file
     *
     * @return void
     */
    private static function sendVkReply(BotUser $botUser, string $text, ?UploadedFile $file): void
    {
        $attachment = $file !== null ? self::uploadVkDocument($botUser, $file) : null;

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
     * Returns null on any error so the message is still sent without a file.
     *
     * @param BotUser      $botUser
     * @param UploadedFile $file
     *
     * @return string|null
     */
    private static function uploadVkDocument(BotUser $botUser, UploadedFile $file): ?string
    {
        // Step 1: get upload server URL
        $serverDto = VkMethods::sendQueryVk('docs.getMessagesUploadServer', [
            'peer_id' => $botUser->chat_id,
        ]);

        if (!is_array($serverDto->response)) {
            return null;
        }

        $uploadUrl = $serverDto->response['upload_url'] ?? null;
        if (empty($uploadUrl)) {
            return null;
        }

        // Step 2: upload the file to VK's server
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            return null;
        }

        $fileHandle = fopen($realPath, 'rb');
        if ($fileHandle === false) {
            return null;
        }

        $uploadResponse = Http::attach('file', $fileHandle, $file->getClientOriginalName())
            ->post($uploadUrl);

        $uploadData = $uploadResponse->json();
        if (empty($uploadData['file'])) {
            return null;
        }

        // Step 3: save the document in VK
        $saveDto = VkMethods::sendQueryVk('docs.save', $uploadData);

        if (!is_array($saveDto->response)) {
            return null;
        }

        $doc = $saveDto->response['doc'] ?? null;
        if (!is_array($doc) || empty($doc['owner_id']) || empty($doc['id'])) {
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
