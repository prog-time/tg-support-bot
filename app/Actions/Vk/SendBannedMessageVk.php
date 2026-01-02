<?php

namespace App\Actions\Vk;

use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkSimpleMessageJob;
use App\Models\BotUser;

class SendBannedMessageVk
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        SendVkSimpleMessageJob::dispatch(
            VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $botUser->chat_id,
                'message' => __('messages.ban_user'),
            ]),
        );
    }
}
