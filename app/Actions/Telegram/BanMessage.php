<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

class BanMessage
{
    /**
     * @param int $messageThreadId
     * @return TelegramAnswerDto
     */
    public static function execute(int $messageThreadId): TelegramAnswerDto
    {
        $dataQuery = [
            'chat_id' => env('TELEGRAM_GROUP_ID'),
            'message_thread_id' => $messageThreadId,
            'text' => __('messages.ban_bot'),
            'parse_mode' => 'html',
        ];
        return TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
    }
}
