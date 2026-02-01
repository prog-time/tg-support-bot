<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\TelegramBot\TelegramMethods;

/**
 * Get file.
 */
class GetFile
{
    /**
     * Get file by fileId.
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
