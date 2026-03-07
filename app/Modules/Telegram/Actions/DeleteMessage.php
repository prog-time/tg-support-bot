<?php

namespace App\Modules\Telegram\Actions;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Modules\Telegram\DTOs\TGTextMessageDto;

/**
 * Delete message.
 */
class DeleteMessage
{
    /**
     * Delete message.
     *
     * @param TGTextMessageDto $queryParams
     *
     * @return TelegramAnswerDto|null
     */
    public function execute(TGTextMessageDto $queryParams): ?TelegramAnswerDto
    {
        try {
            $dataQuery = $queryParams->toArray();
            return TelegramMethods::sendQueryTelegram('deleteMessage', $dataQuery);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
