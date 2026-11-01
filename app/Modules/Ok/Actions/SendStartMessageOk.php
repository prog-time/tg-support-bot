<?php

namespace App\Modules\Ok\Actions;

use App\Models\BotUser;
use App\Modules\Ok\DTOs\OkTextMessageDto;
use App\Modules\Ok\Jobs\SendOkSimpleMessageJob;

class SendStartMessageOk
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        SendOkSimpleMessageJob::dispatch(
            OkTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $botUser->chat_id,
                'text' => __('messages.start'),
            ]),
        );
    }
}
