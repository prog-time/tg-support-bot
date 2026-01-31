<?php

namespace App\Actions\Ai;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

class AiAcceptMessage extends AiAction
{
    /**
     * Confirm AI response sending.
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
                    'methodQuery' => 'editMessageText',
                    'typeSource' => 'supergroup',
                    'chat_id' => config('traffic_source.settings.telegram.group_id'),
                    'message_id' => $messageData->message_id,
                    'message_thread_id' => $update->messageThreadId,
                    'text' => $messageData->text_ai,
                    'parse_mode' => 'html',
                ]),
                'incoming',
            );

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $update,
                TGTextMessageDto::from([
                    'methodQuery' => 'sendMessage',
                    'typeSource' => 'private',
                    'chat_id' => $botUser->chat_id,
                    'text' => $messageData->text_ai,
                    'parse_mode' => 'html',
                ]),
                'outgoing',
            );
        } catch (\Throwable $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());
        }
    }
}
