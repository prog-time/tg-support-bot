<?php

namespace App\Actions\Ai;

use App\Actions\Telegram\SendMessage;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\AiHelper;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

class EditAiMessage
{
    /**
     * Отправка контактной информации
     *
     * @param TelegramUpdateDto $update
     *
     * @return TelegramAnswerDto|null
     */
    public function execute(TelegramUpdateDto $update): ?TelegramAnswerDto
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('Токен от AI бота не указан!', 1);
            }

            $botUser = BotUser::getTelegramUserData($update);
            if (!$botUser) {
                throw new \Exception('Bot user not found');
            }

            $updateText = $update->text;
            if (empty($updateText)) {
                throw new Exception('Текст сообщения не найден!', 1);
            }

            preg_match('/ai_message_edit_[0-9]+/', $updateText, $matches);

            if (empty($matches[0])) {
                throw new Exception('Команда не найдена в тексте!', 1);
            }

            $messageParams = explode('_', $matches[0]);
            if (empty($messageParams[3])) {
                throw new Exception('ID сообщения не найдено!', 1);
            }

            $messageId = $messageParams[3];
            if (!is_numeric($messageId)) {
                throw new Exception('ID сообщения не является числом!', 1);
            }

            $messageData = AiMessage::where('message_id', $messageId)->first();
            if (empty($messageData)) {
                throw new Exception('Сообщение не найдено в БД!', 1);
            }

            $newTextMessage = preg_replace('/^.*\R/', '', $updateText, 1);
            $textMessage = AiHelper::preparedAiAnswer($messageData->text_manager, $newTextMessage);

            $resultQuery = SendMessage::execute($botUser, TGTextMessageDto::from([
                'token' => config('traffic_source.settings.telegram_ai.token'),
                'methodQuery' => 'editMessageText',
                'typeSource' => 'supergroup',
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_id' => $messageData->message_id,
                'message_thread_id' => $update->messageThreadId,
                'text' => $textMessage,
                'parse_mode' => 'html',
                'reply_markup' => AiHelper::preparedAiReplyMarkup((int)$messageData->message_id, $newTextMessage),
            ]));

            if ($resultQuery->ok === false || $resultQuery->response_code !== 200) {
                throw new Exception('Не удалось изменить сообщение в TG!', 1);
            }

            AiMessage::where('message_id', $messageId)->update([
                'text_ai' => $newTextMessage,
            ]);

            $resultDelete = TelegramMethods::sendQueryTelegram('deleteMessage', [
                'chat_id' => $update->chatId,
                'message_id' => $update->messageId,
            ]);

            if ($resultDelete->ok === false || $resultDelete->response_code !== 200) {
                throw new Exception('Не удалось удалить техническое сообщение в TG!', 1);
            }

            return $resultQuery;
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', json_encode($e->getMessage()));
            return null;
        }
    }
}
