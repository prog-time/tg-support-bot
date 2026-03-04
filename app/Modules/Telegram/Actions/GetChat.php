<?php

namespace App\Modules\Telegram\Actions;

use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;

/**
 * Get chat.
 */
class GetChat
{
    /**
     * Get Telegram chat.
     *
     * @param int $chatId
     *
     * @return TelegramAnswerDto
     */
    public function execute(int $chatId): TelegramAnswerDto
    {
        return TelegramMethods::sendQueryTelegram('getChat', [
            'chat_id' => $chatId,
        ]);
    }
}
