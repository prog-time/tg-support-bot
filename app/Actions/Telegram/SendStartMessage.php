<?php

namespace App\Actions\Telegram;

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
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        $dataQuery = [
            'chat_id' => $update->chatId,
            'message_id' => $update->messageId,
        ];
        TelegramMethods::sendQueryTelegram('deleteMessage', $dataQuery);

        if ($update->typeSource === 'private') {
            $dataQuery = [
                'chat_id' => $update->chatId,
                'text' => __('messages.start'),
                'parse_mode' => 'html',
            ];
            TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
        }
    }

}
