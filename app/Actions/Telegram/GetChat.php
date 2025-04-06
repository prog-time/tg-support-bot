<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

/**
 * Get TG chat
 */
class GetChat
{
    /**
     * Getting telegram chat
     * @param int $chatId
     * @return TelegramAnswerDto
     */
    public static function execute(int $chatId): TelegramAnswerDto
    {
        $dataQuery = [
            'chat_id' => $chatId,
        ];
        return TelegramMethods::sendQueryTelegram('getChat', $dataQuery);
    }
}
