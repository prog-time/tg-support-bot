<?php

namespace App\Modules\Email\DTOs;

use Spatie\LaravelData\Data;

/**
 * Outgoing email parameters, handed to {@see \App\Modules\Email\Jobs\SendEmailMessageJob}
 * and sent via {@see \App\Modules\Email\Api\EmailMailer}.
 *
 * `inReplyTo` / `references` carry the sender's original `Message-ID` as a RAW
 * id (no angle brackets) — {@see \App\Modules\Email\Api\EmailMailer} wraps
 * them when building the actual mail headers. Resolved via
 * {@see \App\Modules\Email\Services\EmailThreadStore}.
 *
 * `attachmentPath` is a filesystem path, not an `UploadedFile` — a Livewire
 * temp upload cannot survive queue serialization, so
 * {@see \App\Modules\Admin\Actions\SendReplyAction::copyEmailAttachment()}
 * copies it to a stable path first (mirrors the Telegram document-reply
 * path). `attachmentName`/`attachmentMime` are carried alongside since the
 * copied file itself has neither a meaningful name nor a MIME-typed extension.
 */
class EmailMessageDto extends Data
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $text,
        public ?string $inReplyTo = null,
        public ?string $references = null,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
        public ?string $attachmentMime = null,
    ) {
    }
}
