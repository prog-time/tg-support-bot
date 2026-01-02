<?php

namespace App\Actions\Telegram;

use App\DTOs\TGTextMessageDto;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;

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
