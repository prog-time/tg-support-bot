<?php

namespace App\Modules\Telegram\Actions;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;

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
