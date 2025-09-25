<?php

namespace App\Actions\Ai;

use App\DTOs\TelegramUpdateDto;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

class AiCancelMessage extends AiAction
{
    /**
     * Отмена отправки запроса от AI
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

            $result = TelegramMethods::sendQueryTelegram('deleteMessage', [
                'chat_id' => $update->chatId,
                'message_thread_id' => $update->messageThreadId,
                'message_id' => $messageData->message_id,
            ], config('traffic_source.settings.telegram_ai.token'));

            if ($result->response_code !== 200) {
                throw new Exception('Не удалось удалить сообщение!', 1);
            }

            AiMessage::where('message_id', $messageData->message_id)->delete();

            return true;
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', json_encode($e->getMessage()));
            return false;
        }
    }
}
