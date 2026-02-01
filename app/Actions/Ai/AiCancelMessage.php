<?php

namespace App\Actions\Ai;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

class AiCancelMessage extends AiAction
{
    /**
     * Cancel AI request sending.
     *
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    public function execute(TelegramUpdateDto $update): void
    {
        try {
            if (empty(config('traffic_source.settings.telegram_ai.token'))) {
                throw new Exception('AI bot token not specified!', 1);
            }

            $botUser = BotUser::getOrCreateByTelegramUpdate($update);
            if (!$botUser) {
                throw new Exception('User not found', 1);
            }

            $messageData = $this->getMessageDataByCallbackData($update->callbackData);
            if (empty($messageData)) {
                throw new Exception('Message not found in database!', 1);
            }

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $update,
                TGTextMessageDto::from([
                    'token' => config('traffic_source.settings.telegram_ai.token'),
                    'methodQuery' => 'deleteMessage',
                    'typeSource' => 'private',
                    'chat_id' => $update->chatId,
                    'message_thread_id' => $update->messageThreadId,
                    'message_id' => $messageData->message_id,
                ]),
                'outgoing',
            );

            AiMessage::where('message_id', $messageData->message_id)->delete();
        } catch (\Throwable $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());
        }
    }
}
