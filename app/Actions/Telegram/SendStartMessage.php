<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;

/**
 * Send contact data
 */
class SendStartMessage
{
    /**
     * Sending start message
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
                'text' => __('message.start'),
                'parse_mode' => 'html',
            ];
            TelegramMethods::sendQueryTelegram('sendMessage', $dataQuery);
        }
    }

}
