<?php

namespace App\Modules\Email\DTOs;

/**
 * A parsed incoming email, produced by an {@see \App\Modules\Email\Contracts\EmailInboxReader}
 * implementation. Mirrors the host's *UpdateDto role (AvitoUpdateDto / MaxUpdateDto),
 * adapted for IMAP polling instead of a webhook payload.
 *
 * `chatId` is the sender's email address — the conversation key used to find/create
 * the BotUser (`ToTgMessageService::__construct()` reads `$update->chatId`).
 *
 * `providerRef` is an opaque handle owned by the reader implementation (e.g. the
 * underlying `Webklex\PHPIMAP\Message` instance) used by
 * {@see \App\Modules\Email\Contracts\EmailInboxReader::markSeen()} to flag the
 * message as seen on the server after successful processing. Callers outside the
 * reader must not rely on its type.
 */
readonly class EmailUpdateDto
{
    public function __construct(
        public string $chatId,
        public string $senderName,
        public string $subject,
        public ?string $text,
        public string $messageId,
        public ?string $references,
        public array $attachments = [],
        public mixed $providerRef = null,
    ) {
    }
}
