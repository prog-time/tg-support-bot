<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\Jobs\SendMessage\SendAiResponseMessageJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use Exception;

class SendAiAnswerMessage
{
    /**
     * Выполнить обработку AI-сообщения для Telegram.
     *
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('Неуказан токен для AI бота!');
            }

            $botUser = BotUser::getOrCreateByTelegramUpdate($update);
            if (!$botUser) {
                throw new Exception('Пользователь не найден!', 1);
            }

            SendAiResponseMessageJob::dispatch(
                $botUser->id,
                $update,
            );
        } catch (\Exception $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());
        }
    }
}
