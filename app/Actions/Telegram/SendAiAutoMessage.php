<?php

namespace App\Actions\Telegram;

use App\DTOs\Ai\AiResponseDto;
use App\Models\BotUser;
use App\Logging\LokiLogger;
use App\DTOs\Ai\AiRequestDto;
use App\DTOs\TGTextMessageDto;
use App\DTOs\TelegramUpdateDto;
use App\Services\Ai\AiAssistantService;

class SendAiAutoMessage
{
    public function __construct(
        private readonly AiAssistantService $aiService
    ) {
    }

    /**
     * Выполнить обработку AI-сообщения для Telegram.
     *
     * @param TelegramUpdateDto $update DTO с данными обновления Telegram
     */
    public function execute(TelegramUpdateDto $update): void
    {
        try {
            if (config('traffic_source.settings.telegram_ai.token')) {
                $botUser = BotUser::getTelegramUserData($update);
                if (!$botUser) {
                    throw new \Exception('Bot user not found');
                }

                // Создать AI-запрос
                $aiRequest = new AiRequestDto(
                    message: $update->text ?? '',
                    userId: $botUser->id,
                    platform: 'telegram',
                    provider: config('ai.default_provider'),
                    forceEscalation: false
                );

                // Обработать через AI
                $aiResponse = $this->aiService->processMessage($aiRequest);

                if ($aiResponse) {
                    // Отправить AI-ответ
                    $this->sendAiResponse($update, $aiResponse, $botUser);
                }
            }
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());
        }
    }

    /**
     * Отправить AI-ответ пользователю.
     *
     * @param TelegramUpdateDto $update DTO с данными обновления
     * @param AiResponseDto $aiResponse DTO с ответом AI
     * @param BotUser $botUser Пользователь бота
     */
    private function sendAiResponse(TelegramUpdateDto $update, AiResponseDto $aiResponse, BotUser $botUser): void
    {
        $this->sendTelegramMessage($botUser, $aiResponse->response, 'Markdown');
    }

    /**
     * Отправить сообщение в Telegram используя существующую инфраструктуру.
     *
     * @param BotUser $botUser Пользователь бота
     * @param string $text Текст сообщения
     * @param string|null $parseMode Режим парсинга (Markdown, HTML)
     */
    private function sendTelegramMessage(BotUser $botUser, string $text, string $parseMode = null): void
    {
        $this->sendPrivateMessage($botUser, $text, $parseMode);
        $this->sendGroupMessage($botUser, $text, $parseMode);
    }

    /**
     * Отправка в личку пользователю
     *
     * @param BotUser $botUser
     * @param string $text
     * @param string|null $parseMode
     * @return void
     */
    private function sendPrivateMessage(BotUser $botUser, string $text, string $parseMode = null): void
    {
        SendMessage::execute($botUser, TGTextMessageDto::from([
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => $botUser->chat_id,
            'text' => "🤖 AI-помощник:\n\n" . $text,
            'parse_mode' => $parseMode ?? 'html',
        ]));
    }

    /**
     * Отправка в группу с чатами
     *
     * @param BotUser $botUser
     * @param string $text
     * @param string|null $parseMode
     * @return void
     */
    private function sendGroupMessage(BotUser $botUser, string $text, string $parseMode = null): void
    {
        SendMessage::execute($botUser, TGTextMessageDto::from([
            'token' => config('traffic_source.settings.telegram_ai.token'),
            'methodQuery' => 'sendMessage',
            'typeSource' => 'private',
            'chat_id' => config('traffic_source.settings.telegram.group_id'),
            'message_thread_id' => $botUser->topic_id,
            'text' => $text,
            'parse_mode' => $parseMode ?? 'html',
        ]));
    }
}
