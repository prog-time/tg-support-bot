<?php

namespace App\Modules\Email\Actions;

use App\Models\BotUser;
use App\Modules\Email\DTOs\EmailMessageDto;
use App\Modules\Email\Jobs\SendEmailMessageJob;
use App\Modules\Email\Services\EmailThreadStore;

/**
 * Notify a banned email user. Mirrors the host's SendBannedMessageAvito
 * action. Reuses the host's translation key so wording stays consistent
 * across channels.
 */
class SendBannedMessageEmail
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
                'text' => __('messages.ban_user'),
                'inReplyTo' => $headers['inReplyTo'],
                'references' => $headers['references'],
            ]),
        );
    }
}
