<?php

namespace App\Modules\Email\Contracts;

use App\Modules\Email\DTOs\EmailUpdateDto;

/**
 * Abstraction over the mailbox read side so {@see \App\Modules\Email\Console\PollInboxCommand}
 * and {@see \App\Modules\Email\Services\EmailMessageService} never touch the IMAP
 * library directly — tests mock this interface instead of opening a real connection.
 *
 * The real implementation is {@see \App\Modules\Email\Api\EmailImapClient}, bound
 * in {@see \App\Modules\Email\EmailServiceProvider}.
 */
interface EmailInboxReader
{
    /**
     * Fetch all currently unseen inbox messages, parsed into DTOs.
     *
     * Implementations MUST leave the server-side `\Seen` flag untouched here —
     * the caller decides whether the message was successfully processed and
     * calls {@see self::markSeen()} only then. A poll run that fails midway
     * must not lose or duplicate an email on the next run.
     *
     * @return EmailUpdateDto[]
     */
    public function fetchUnseen(): array;

    /**
     * Mark a message as seen on the mail server.
     *
     * Called only after the message has been fully processed (BotUser
     * resolved, message persisted / forwarded, AI dispatched if applicable).
     */
    public function markSeen(EmailUpdateDto $update): void;
}
