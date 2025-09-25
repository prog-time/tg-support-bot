<?php

namespace App\Actions\Ai;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

class AiAcceptMessage extends AiAction
{
    /**
     * Подтверждение отправки ответа от AI
     *
     * @param TelegramUpdateDto $update
     *
     * @return bool
     */
    public function execute(TelegramUpdateDto $update): bool
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('Токен от AI бота не указан!', 1);
            }

            $botUser = BotUser::getTelegramUserData($update);
            if (!$botUser) {
                throw new Exception('Пользователь не найден', 1);
            }

            $messageData = $this->getMessageDataByCallbackData($update->callbackData);
            if (empty($messageData)) {
                throw new Exception('Сообщение не найдено в БД!', 1);
            }

            SendMessage::execute($botUser, TGTextMessageDto::from([
                'token' => config('traffic_source.settings.telegram_ai.token'),
                'methodQuery' => 'editMessageText',
                'typeSource' => 'supergroup',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_id' => $messageData->message_id,
                'message_thread_id' => $update->messageThreadId,
                'text' => $messageData->text_ai,
                'parse_mode' => 'html',
            ]));

            TelegramMethods::sendQueryTelegram('sendMessage', [
                'chat_id' => $botUser->chat_id,
                'text' => $messageData->text_ai,
                'parse_mode' => 'html',
            ]);

            return true;
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', json_encode($e->getMessage()));
            return false;
        }
    }
}
