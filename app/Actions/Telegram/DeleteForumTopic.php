<?php

namespace App\Actions\Telegram;

use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;

/**
 * Удаление топика
 */
class DeleteForumTopic
{
    /**
     * Удаление сообщения
     *
     * @param BotUser $botUser
     *
     * @return void
     */
    public static function execute(BotUser $botUser): void
    {
        TelegramMethods::sendQueryTelegram('deleteForumTopic', [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
        ]);
    }
}
