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
 *
 * `attachments` holds at most one entry — Telegram sends one photo/document per
 * message — shaped `array{path: string, storedPath: string, name: string, mime: string}`:
 * `path` is a queue-safe temp copy for the outgoing Telegram upload (consumed/
 * deleted by it), `storedPath` is a permanent `local`-disk copy under
 * `chat-attachments/` for the admin workspace. Populated by
 * {@see \App\Modules\Email\Api\EmailImapClient::extractAttachments()}.
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

    /**
     * The body prefixed with its subject, for every reader of this email
     * that a human (or the AI) looks at directly — the persisted `messages`
     * row, the AI context, and the source text for the Telegram forward.
     * Neither a Telegram forum topic nor the admin workspace has a separate
     * place to show a subject line, so it's folded into the text itself.
     *
     * This is deliberately the single source of truth for "what was said":
     * {@see \App\Modules\Email\Services\EmailMessageService::sendMessage()}
     * HTML-escapes a *copy* of this string only for the outgoing Telegram
     * payload — the stored/AI-facing text here must stay unescaped plain text.
     *
     * @return string
     */
    public function displayText(): string
    {
        $subject = trim($this->subject);
        $body = (string) $this->text;

        if ($subject === '') {
            return $body;
        }

        return "Тема: {$subject}\n\n{$body}";
    }

    /**
     * A copy safe to hand to a queued job (e.g. {@see \App\Modules\Telegram\Jobs\SendEmailTelegramMessageJob}).
     *
     * `providerRef` holds a live, non-serialization-safe object owned by the
     * reader (e.g. a `Webklex\PHPIMAP\Message`, carrying raw MIME bytes and
     * IMAP client internals). `Illuminate\Queue\SerializesModels` `serialize()`s
     * the whole job — including this DTO — to build the queue payload, even
     * under the `sync` driver. For an email with an attachment, the resulting
     * string is not valid UTF-8, `json_encode()` fails ("Malformed UTF-8
     * characters"), the dispatch throws, and — since nothing downstream
     * expects that — the source email is never marked seen and is retried
     * forever on every poll. Stripping the ref here breaks nothing: no job
     * reads `providerRef`, only {@see \App\Modules\Email\Contracts\EmailInboxReader::markSeen()}
     * does, which is always called with the ORIGINAL (non-stripped) instance.
     *
     * @return self
     */
    public function withoutProviderRef(): self
    {
        return new self(
            chatId: $this->chatId,
            senderName: $this->senderName,
            subject: $this->subject,
            text: $this->text,
            messageId: $this->messageId,
            references: $this->references,
            attachments: $this->attachments,
            providerRef: null,
        );
    }
}
