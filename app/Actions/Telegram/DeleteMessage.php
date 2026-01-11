<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\TelegramBot\TelegramMethods;

/**
 * Удаление сообщения
 */
class DeleteMessage
{
    /**
     * Удаление сообщения
     *
     * @param TGTextMessageDto $queryParams
     *
     * @return TelegramAnswerDto|null
     */
    public static function execute(TGTextMessageDto $queryParams): ?TelegramAnswerDto
    {
        try {
            $dataQuery = $queryParams->toArray();
            return TelegramMethods::sendQueryTelegram('deleteMessage', $dataQuery);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
