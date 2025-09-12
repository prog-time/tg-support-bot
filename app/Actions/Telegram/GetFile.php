<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

/**
 * Получение файла
 */
class GetFile
{
    /**
     * Получение файла по fileId
     *
     * @param string $fileId
     *
     * @return TelegramAnswerDto
     */
    public static function execute(string $fileId): TelegramAnswerDto
    {
        return TelegramMethods::sendQueryTelegram('getFile', [
            'file_id' => $fileId,
        ]);
    }
}
