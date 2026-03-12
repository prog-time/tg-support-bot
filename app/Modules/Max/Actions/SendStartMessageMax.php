<?php

namespace App\Modules\Max\Actions;

use App\Models\BotUser;
use App\Modules\Max\DTOs\MaxTextMessageDto;
use App\Modules\Max\Jobs\SendMaxSimpleMessageJob;

class SendStartMessageMax
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        SendMaxSimpleMessageJob::dispatch(
            MaxTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'user_id' => $botUser->chat_id,
                'text' => __('messages.start'),
            ]),
        );
    }
}
