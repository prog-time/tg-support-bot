<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

/**
 * Получение чата
 */
class GetChat
{
    /**
     * Получение telegram чата
     *
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
