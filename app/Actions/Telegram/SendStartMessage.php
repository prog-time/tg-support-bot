<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Models\BotUser;
use App\Services\Button\ButtonParser;
use App\Services\Button\KeyboardBuilder;
use App\TelegramBot\TelegramMethods;

/**
 * Send start message.
 */
class SendStartMessage
{
    /**
     * Send start message.
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
            $buttonParser = new ButtonParser();
            $keyboardBuilder = new KeyboardBuilder();

            $parsedMessage = $buttonParser->parse(__('messages.start'));
            $keyboard = $keyboardBuilder->buildTelegramKeyboard($parsedMessage);

            $messageParamsDTO = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $update->chatId,
                'message_thread_id' => $update->messageThreadId,
                'text' => $parsedMessage->text,
                'parse_mode' => 'html',
                'reply_markup' => $keyboard,
            ]);

            $botUser = BotUser::getOrCreateByTelegramUpdate($update);

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $update,
                $messageParamsDTO,
                'outgoing'
            );
        }
    }
}
