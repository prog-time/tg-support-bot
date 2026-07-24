<?php

namespace App\Modules\Avito\Actions;

use App\Models\BotUser;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;

/**
 * Notify a banned Avito user. Mirrors the host's SendBannedMessageMax action.
 * Reuses the host's translation key so wording stays consistent across channels.
 */
class SendBannedMessageAvito
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        SendAvitoSimpleMessageJob::dispatch(
            AvitoTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $botUser->chat_id,
                'text' => __('messages.ban_user'),
            ]),
        );
    }
}
