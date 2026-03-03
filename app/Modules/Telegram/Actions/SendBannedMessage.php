<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;

class SendBannedMessage
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => __('messages.ban_user'),
            'parse_mode' => 'html',
        ]));
    }
}
