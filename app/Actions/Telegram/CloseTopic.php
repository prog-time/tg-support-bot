<?php

namespace App\Actions\Telegram;

use App\DTOs\TGTextMessageDto;
use App\DTOs\Vk\VkTextMessageDto;
use App\Jobs\SendMessage\SendVkSimpleMessageJob;
use App\Jobs\SendTelegramSimpleQueryJob;
use App\Models\BotUser;

class CloseTopic
{
    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function execute(BotUser $botUser): void
    {
        if ($botUser->isClosed()) {
            return;
        }

        $groupId = config('traffic_source.settings.telegram.group_id');

        switch ($botUser->platform) {
            case 'telegram':
                $this->sendMessageInTelegram($botUser);
                break;

            case 'vk':
                $this->sendMessageInVk($botUser);
                break;
        }

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'editForumTopic',
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
            'icon_custom_emoji_id' => __('icons.outgoing'),
        ]));

        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'closeForumTopic',
            'chat_id' => $groupId,
            'message_thread_id' => $botUser->topic_id,
        ]));

        $botUser->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function sendMessageInTelegram(BotUser $botUser): void
    {
        SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'chat_id' => $botUser->chat_id,
            'text' => __('messages.message_close_topic'),
            'parse_mode' => 'html',
        ]));
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    public function sendMessageInVk(BotUser $botUser): void
    {
        SendVkSimpleMessageJob::dispatch(
            VkTextMessageDto::from([
                'methodQuery' => 'messages.send',
                'peer_id' => $botUser->chat_id,
                'message' => __('messages.message_close_topic'),
            ]),
        );
    }
}
