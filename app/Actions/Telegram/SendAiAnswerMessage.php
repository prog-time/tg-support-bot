<?php

namespace App\Actions\Telegram;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\TelegramAnswerDto;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\AiHelper;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use App\Services\Ai\AiAssistantService;
use App\TelegramBot\TelegramMethods;
use Exception;

class SendAiAnswerMessage
{
    /**
     * Выполнить обработку AI-сообщения для Telegram.
     *
     * @param TelegramUpdateDto $update
     *
     * @return TelegramAnswerDto|null
     */
    public function execute(TelegramUpdateDto $update): ?TelegramAnswerDto
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('Неуказан токен для AI бота!');
            }

            $botUser = BotUser::getTelegramUserData($update);
            if (!$botUser) {
                throw new Exception('Пользователь не найден!', 1);
            }

            $managerTextMessage = trim(str_replace('/ai_generate', '', $update->text));
            if (empty($managerTextMessage)) {
                throw new Exception('Сообщение пустое!', 1);
            }

            // Создать AI-запрос
            $aiRequest = new AiRequestDto(
                message: $managerTextMessage,
                userId: $botUser->id,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            );

            // Обработать через AI
            $aiService = new AiAssistantService();
            $aiResponse = $aiService->processMessage($aiRequest);

            if (empty($aiResponse)) {
                throw new Exception('Не удалось отправить запрос в AI!', 1);
            }

            // Отправить AI-ответ
            $sendAiMessage = $this->sendAiResponse($managerTextMessage, $aiResponse->response, $botUser);

            if ($sendAiMessage->response_code !== 200) {
                throw new Exception('Не удалось отправить запрос в Telegram!', 1);
            }

            $messageData = AiMessage::create([
                'bot_user_id' => $botUser->id,
                'message_id' => $sendAiMessage->message_id,
                'text_ai' => $aiResponse->response,
                'text_manager' => $managerTextMessage,
            ]);

            $resultEditMessage = $this->editAiResponse($botUser, $sendAiMessage, $messageData);

            if ($resultEditMessage->response_code !== 200) {
                throw new Exception('Не удалось добавить клавиатуру!', 1);
            }

            TelegramMethods::sendQueryTelegram('deleteMessage', [
                'chat_id' => $update->chatId,
                'message_thread_id' => $update->messageThreadId,
                'message_id' => $update->messageId,
            ]);

            return $resultEditMessage;
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());

            return null;
        }
    }

    /**
     * @param string  $managerText
     * @param string  $aiText
     * @param BotUser $botUser
     *
     * @return TelegramAnswerDto
     */
    private function sendAiResponse(string $managerText, string $aiText, BotUser $botUser): TelegramAnswerDto
    {
        return SendMessage::execute($botUser, TGTextMessageDto::from([
            'token' => config('traffic_source.settings.telegram_ai.token'),
            'methodQuery' => 'sendMessage',
            'typeSource' => 'supergroup',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => AiHelper::preparedAiAnswer($managerText, $aiText),
            'parse_mode' => 'html',
        ]));
    }

    /**
     * @param BotUser           $botUser
     * @param TelegramAnswerDto $sendAiMessage
     * @param AiMessage         $messageData
     *
     * @return TelegramAnswerDto
     */
    private function editAiResponse(BotUser $botUser, TelegramAnswerDto $sendAiMessage, AiMessage $messageData): TelegramAnswerDto
    {
        return SendMessage::execute($botUser, TGTextMessageDto::from([
            'token' => config('traffic_source.settings.telegram_ai.token'),
            'methodQuery' => 'editMessageText',
            'typeSource' => 'supergroup',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_id' => $sendAiMessage->message_id,
            'message_thread_id' => $sendAiMessage->message_thread_id,
            'text' => $sendAiMessage->text,
            'parse_mode' => 'html',
            'reply_markup' => AiHelper::preparedAiReplyMarkup($sendAiMessage->message_id, $messageData->text_ai),
        ]));
    }
}
