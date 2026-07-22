<?php

namespace App\Modules\Email\Jobs;

use App\Modules\Email\Api\EmailMailer;
use App\Modules\Email\DTOs\EmailMessageDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send a "fire-and-forget" email (no message persistence) over SMTP.
 *
 * This is the sole outgoing-mail job for the channel — mirrors
 * {@see \App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob}: every call site
 * (manager reply, AI answer, banned/start notice, feedback prompt) persists
 * its own `messages` row (or intentionally does not, for banned/start/feedback)
 * BEFORE dispatching this job, so it never writes to the database itself.
 */
class SendEmailMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public function __construct(public EmailMessageDto $queryParams)
    {
    }

    /**
     * @param EmailMailer $mailer
     *
     * @return void
     */
    public function handle(EmailMailer $mailer): void
    {
        try {
            $mailer->send($this->queryParams);
        } catch (\Throwable $e) {
            Log::channel('app')->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }
}
