<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\TelegramBot\TelegramMethods;

/**
 * Отправка стартового сообщения
 */
class SendStartMessage
{
    /**
     * Отправка стартового сообщения
     *
     * @param TelegramUpdateDto $update
     *
     * @return TelegramAnswerDto|null
     */
    public function execute(TelegramUpdateDto $update): ?TelegramAnswerDto
    {
        TelegramMethods::sendQueryTelegram('deleteMessage', [
            'chat_id' => $update->chatId,
            'message_id' => $update->messageId,
        ]);

        if ($update->typeSource !== 'private') {
            return null;
        }

        return TelegramMethods::sendQueryTelegram('sendMessage', [
            'chat_id' => $update->chatId,
            'text' => __('messages.start'),
            'parse_mode' => 'html',
        ]);
    }
}
