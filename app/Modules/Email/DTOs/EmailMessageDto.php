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
 */
class EmailMessageDto extends Data
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $text,
        public ?string $inReplyTo = null,
        public ?string $references = null,
    ) {
    }
}
