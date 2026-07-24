<?php

namespace App\Modules\Email\Actions;

use App\Models\BotUser;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;

/**
 * Send the greeting on first contact. Mirrors the host's SendStartMessageAvito
 * action and reuses the host's translation key.
 *
 * Not currently wired to a trigger: unlike Telegram/MAX, there is no distinct
 * "first contact" event for email — a new sender's first message is just a
 * normal incoming email, handled by the regular funnel. This mirrors
 * SendStartMessageAvito, which is likewise defined for API parity across
 * channels but not invoked by AvitoBotController today.
 */
class SendStartMessageEmail
{
    public function __construct(private readonly EmailThreadStore $threadStore)
    {
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        $headers = $this->threadStore->replyHeaders($botUser->id);

        SendEmailMessageJob::dispatch(
            EmailMessageDto::from([
                'to' => $botUser->chat_id,
                'subject' => $headers['subject'],
                'text' => __('messages.start'),
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
            ]),
        );
    }
}
