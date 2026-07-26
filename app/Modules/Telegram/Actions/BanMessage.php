<?php

namespace App\Modules\Telegram\Actions;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TGTextMessageDto;
use App\Modules\Telegram\Jobs\SendTelegramMessageJob;
use App\Modules\Telegram\Jobs\SendTelegramSimpleQueryJob;
use App\Services\Settings\SettingsService;

class BanMessage
{
    /**
     * Send message indicating that user has blocked the bot, and flip the
     * topic icon to the "done" checkmark so managers can see at a glance —
     * without opening the topic — that the user won't receive further
     * replies (issue #114).
     *
     * The icon update is skipped when the topic doesn't exist yet: the
     * dispatched SendTelegramMessageJob creates one on demand (via
     * TopicCreateJob) using the default "incoming" icon, so there's nothing
     * to edit here yet.
     *
     * @param int   $botUserId
     * @param mixed $update
     *
     * @return void
     */
    public function execute(int $botUserId, mixed $update): void
    {
        $botUser = BotUser::find($botUserId);
        $groupId = (string) app(SettingsService::class)->get('telegram.group_id');

        SendTelegramMessageJob::dispatch(
            $botUser->id,
            $update,
            TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'typeSource' => 'supergroup',
                'chat_id' => $groupId,
                'message_thread_id' => $botUser->topic_id,
                'text' => __('messages.ban_bot'),
                'parse_mode' => 'html',
            ]),
            'incoming',
        );

        if (!empty($botUser->topic_id) && $groupId !== '') {
            SendTelegramSimpleQueryJob::dispatch(TGTextMessageDto::from([
                'methodQuery' => 'editForumTopic',
                'chat_id' => $groupId,
                'message_thread_id' => $botUser->topic_id,
                'icon_custom_emoji_id' => __('icons.blocked'),
            ]));
        }
    }
}
