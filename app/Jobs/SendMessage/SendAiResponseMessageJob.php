<?php

namespace App\Jobs\SendMessage;

use App\DTOs\Ai\AiRequestDto;
use App\DTOs\TelegramUpdateDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Services\Ai\AiAssistantService;
use App\TelegramBot\TelegramMethods;

class SendAiResponseMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public string $typeMessage = 'incoming';

    public mixed $telegramMethods;

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        mixed $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $managerTextMessage = trim(str_replace('/ai_generate', '', $this->updateDto->text));
            if (empty($managerTextMessage)) {
                throw new \Exception('Сообщение пустое!', 1);
            }

            // Создать AI-запрос
            $aiRequest = new AiRequestDto(
                message: $managerTextMessage,
                userId: $this->botUserId,
                platform: 'telegram',
                provider: config('ai.default_provider'),
                forceEscalation: false
            );

            // Обработать через AI
            $aiService = new AiAssistantService();
            $aiResponse = $aiService->processMessage($aiRequest);

            if (empty($aiResponse)) {
                throw new \Exception('Не удалось отправить запрос в AI!', 1);
            }

            // отправка запроса в Telegram
            SendAiTelegramMessageJob::dispatch(
                $botUser->id,
                $this->updateDto,
                $managerTextMessage,
                $aiResponse->response
            );
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }

    /**
     * Сохраняем сообщение в базу после успешной отправки
     *
     * @param mixed $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
