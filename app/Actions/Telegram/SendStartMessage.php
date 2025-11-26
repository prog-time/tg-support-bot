<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
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
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        TelegramMethods::sendQueryTelegram('deleteMessage', [
            'chat_id' => $update->chatId,
            'message_id' => $update->messageId,
        ]);

        if ($update->typeSource === 'private') {
            $messageParamsDTO = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $update->chatId,
                'message_thread_id' => $update->messageThreadId,
                'text' => __('messages.start'),
                'parse_mode' => 'html',
            ]);

            $botUser = BotUser::getTelegramUserData($update);

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $update,
                $messageParamsDTO,
                'outgoing'
            );
        }
    }
}
