<?php

namespace App\Modules\Avito\Actions;

use App\Models\BotUser;
use App\Modules\Avito\DTOs\AvitoTextMessageDto;
use App\Modules\Avito\Jobs\SendAvitoSimpleMessageJob;

/**
 * Send the greeting on first contact. Mirrors the host's SendStartMessageMax
 * action and reuses the host's translation key.
 */
class SendStartMessageAvito
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
                'text' => __('messages.start'),
            ]),
        );
    }
}
