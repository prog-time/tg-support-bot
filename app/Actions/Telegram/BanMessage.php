<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

class BanMessage
{
    /**
     * Сообщение о том, что пользователь забанил бота
     *
     * @param int $messageThreadId
     *
     * @return TelegramAnswerDto
     */
    public static function execute(int $messageThreadId): TelegramAnswerDto
    {
        $dataQuery = [
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $messageThreadId,
            'text' => __('messages.ban_bot'),
            'parse_mode' => 'html',
        ];
        return TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
    }
}
