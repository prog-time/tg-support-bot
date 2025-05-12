<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

/**
 * Get TG chat
 */
class GetFile
{
    /**
     * Getting file
     * @param string $fileId
     * @return TelegramAnswerDto
     */
    public static function execute(string $fileId): TelegramAnswerDto
    {
        $dataQuery = [
            'file_id' => $fileId,
        ];
        return TelegramMethods::sendQueryTelegram('getFile', $dataQuery);
    }
}
