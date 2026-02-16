<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramUpdateDto;
use App\Jobs\SendMessage\SendAiResponseMessageJob;
use Illuminate\Support\Facades\Log;
use App\Models\BotUser;
use Exception;

class SendAiAnswerMessage
{
    /**
     * Process AI message for Telegram.
     *
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('AI bot token not specified!');
            }

            $botUser = BotUser::getOrCreateByTelegramUpdate($update);
            if (!$botUser) {
                throw new Exception('User not found!', 1);
            }

            SendAiResponseMessageJob::dispatch(
                $botUser->id,
                $update,
            );
        } catch (\Throwable $e) {
            Log::channel('loki')->error($e->getMessage(), ['source' => 'ai_error']);
        }
    }
}
