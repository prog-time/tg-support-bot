<?php

namespace App\Modules\Email\Api;

use App\Modules\Email\Contracts\EmailInboxReader;
use App\Modules\Email\DTOs\EmailUpdateDto;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * IMAP inbox reader backed by webklex/php-imap (pure PHP protocol client —
 * ext/imap is NOT required).
 *
 * A fresh connection is opened for every {@see self::fetchUnseen()} call and
 * closed before returning. The scheduled `email:poll` command runs once a
 * minute; keeping a connection alive between runs risks a stale/timed-out
 * session for no real benefit, so "reconnect every run" was chosen over
 * "hold the connection open" (see issue #214's open question on this).
 *
 * Attachments are not parsed in this iteration — the email channel is
 * text-only in v1, mirroring the Avito module's first iteration.
 */
class EmailImapClient implements EmailInboxReader
{
    private const FOLDER = 'INBOX';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return EmailUpdateDto[]
     */
    public function fetchUnseen(): array
    {
        $client = $this->connect();
        if ($client === null) {
            return [];
        }

        try {
            $folder = $client->getFolder(self::FOLDER);
            if ($folder === null) {
                Log::channel('app')->error('EmailImapClient: INBOX folder not found');

                return [];
            }

            $messages = $folder->query()
                ->unseen()
                ->leaveUnread()
                ->softFail()
                ->get();

            $updates = [];
            foreach ($messages as $message) {
                if (!$message instanceof Message) {
                    continue;
                }

                $dto = $this->toDto($message);
                if ($dto !== null) {
                    $updates[] = $dto;
                }
            }

            return $updates;
        } catch (\Throwable $e) {
            Log::channel('app')->error('EmailImapClient: fetchUnseen failed | ' . $e->getMessage());

            return [];
        } finally {
            try {
                $client->disconnect();
            } catch (\Throwable) {
                // Best-effort close.
            }
        }
    }

    /**
     * @param EmailUpdateDto $update
     */
    public function markSeen(EmailUpdateDto $update): void
    {
        if (!$update->providerRef instanceof Message) {
            return;
        }

        try {
            $update->providerRef->setFlag('Seen');
        } catch (\Throwable $e) {
            Log::channel('app')->error('EmailImapClient: markSeen failed | ' . $e->getMessage());
        }
    }

    /**
     * @return Client|null
     */
    private function connect(): ?Client
    {
        $host = (string) ($this->settings->get('email.imap_host') ?? '');
        $port = (int) ($this->settings->get('email.imap_port') ?: 993);
        $encryption = (string) ($this->settings->get('email.imap_encryption') ?? 'ssl');
        $username = (string) ($this->settings->get('email.username') ?? '');
        $password = (string) ($this->settings->get('email.password') ?? '');

        if ($host === '' || $username === '' || $password === '') {
            return null;
        }

        try {
            $manager = new ClientManager();
            $client = $manager->make([
                'host' => $host,
                'port' => $port,
                'protocol' => 'imap',
                'encryption' => $encryption !== '' ? $encryption : false,
                'validate_cert' => true,
                'username' => $username,
                'password' => $password,
            ]);

            $client->connect();

            return $client;
        } catch (\Throwable $e) {
            Log::channel('app')->error('EmailImapClient: connect failed | ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @param Message $message
     *
     * @return EmailUpdateDto|null
     */
    private function toDto(Message $message): ?EmailUpdateDto
    {
        try {
            $from = $this->firstAddress($message);
            if ($from === null || $from->mail === '') {
                return null;
            }

            $text = $this->extractText($message);

            $personal = $this->decodeMimeHeader((string) $from->personal);

            return new EmailUpdateDto(
                chatId: $from->mail,
                senderName: $personal !== '' ? $personal : $from->mail,
                subject: $this->decodeMimeHeader((string) $message->subject),
                text: $text !== '' ? trim($text) : null,
                messageId: (string) $message->message_id,
                references: $this->referencesHeader($message),
                attachments: $this->extractAttachments($message),
                providerRef: $message,
            );
        } catch (\Throwable $e) {
            Log::channel('app')->error('EmailImapClient: failed to parse message | ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Decode an RFC 2047 «encoded-word» header value (e.g. a From name or a
     * Subject like «=?UTF-8?B?…?=») to plain UTF-8.
     *
     * webklex hands some header fields back still encoded, which otherwise
     * surfaces verbatim as the conversation title. A value that is not encoded
     * passes through unchanged; on a decode error the original is kept.
     *
     * @param string $value
     *
     * @return string
     */
    private function decodeMimeHeader(string $value): string
    {
        if ($value === '' || !str_contains($value, '=?')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded !== false ? trim($decoded) : $value;
    }

    /**
     * @param Message $message
     *
     * @return Address|null
     */
    private function firstAddress(Message $message): ?Address
    {
        // Message::__get() always resolves an unknown property through
        // Header::get(), whose return type is declared `Attribute` (never
        // null) — see webklex/php-imap's Header::get().
        $value = $message->from->get(0);

        return $value instanceof Address ? $value : null;
    }

    /**
     * Prefer the plain-text body; fall back to a stripped/decoded HTML body.
     *
     * @param Message $message
     *
     * @return string
     */
    private function extractText(Message $message): string
    {
        if ($message->hasTextBody()) {
            return $message->getTextBody();
        }

        if ($message->hasHTMLBody()) {
            $plain = strip_tags($message->getHTMLBody());

            return html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Build a `References` header value (space-separated, angle-bracketed ids)
     * from the parsed `references` attribute.
     *
     * @param Message $message
     *
     * @return string|null
     */
    private function referencesHeader(Message $message): ?string
    {
        $ids = [];
        foreach ($message->references->toArray() as $value) {
            foreach ((array) $value as $inner) {
                $id = trim((string) $inner);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return $ids !== [] ? implode(' ', array_map(static fn (string $id) => "<{$id}>", $ids)) : null;
    }

    /**
     * Extract the first attachment (if any) to two on-disk copies:
     *
     *  - `path` — a queue-safe temp file for the Telegram upload
     *    (`{@see \App\Modules\Telegram\Api\ParserMethods::attachQuery()}` deletes
     *    it after a successful send, so it must never be the only copy);
     *  - `storedPath` — a permanent copy on the `local` disk under
     *    `chat-attachments/`, so the admin workspace can render/download it via
     *    the same route outgoing manager-reply attachments use.
     *
     * Only the first attachment is kept — Telegram's `sendPhoto`/`sendDocument`
     * carry one file per message, mirroring the single-file limit already in
     * place for outgoing replies (`SendReplyAction::sendEmailReply()`).
     *
     * @param Message $message
     *
     * @return array<int, array{path: string, storedPath: string, name: string, mime: string}>
     */
    private function extractAttachments(Message $message): array
    {
        if (!$message->hasAttachments()) {
            return [];
        }

        /** @var Attachment|null $attachment */
        $attachment = $message->getAttachments()->first();
        if ($attachment === null) {
            return [];
        }

        $content = (string) $attachment->content;
        if ($content === '') {
            return [];
        }

        $name = $this->decodeMimeHeader((string) ($attachment->name ?: 'attachment'));
        $mime = $attachment->getMimeType() ?: 'application/octet-stream';
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: (string) $attachment->getExtension();

        $storedPath = 'chat-attachments/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        Storage::disk('local')->put($storedPath, $content);

        $dir = storage_path('app/temp_attachments');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $tempPath = $dir . '/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
        file_put_contents($tempPath, $content);

        return [[
            'path' => $tempPath,
            'storedPath' => $storedPath,
            'name' => $name,
            'mime' => $mime,
        ]];
    }
}
