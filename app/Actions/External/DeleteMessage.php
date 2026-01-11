<?php

namespace App\Actions\External;

use App\DTOs\External\ExternalMessageDto;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\Message;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

/**
 * Удаление сообщения
 */
class DeleteMessage
{
    /**
     * Удаление сообщения
     *
     * @param ExternalMessageDto $updateData
     *
     * @return void
     */
    public static function execute(ExternalMessageDto $updateData): void
    {
        try {
            $externalUser = ExternalUser::where([
                'external_id' => $updateData->external_id,
            ])->first();
            if (empty($externalUser)) {
                throw new Exception('Чат не найден!', 1);
            }

            $botUser = BotUser::where([
                'chat_id' => $externalUser->id,
                'platform' => $externalUser->source,
            ])->first();
            if (empty($botUser)) {
                throw new Exception('Чат не найден!', 1);
            }

            $whereParamsMessage = [
                'message_type' => 'incoming',
                'platform' => $externalUser->source,
                'from_id' => $updateData->message_id,
            ];

            $messageData = Message::where($whereParamsMessage)->first();
            if (empty($messageData)) {
                throw new Exception('Сообщение не найдено!', 1);
            }

            TelegramMethods::sendQueryTelegram('deleteMessage', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'message_id' => $messageData->to_id,
                'message_thread_id' => $botUser->topic_id,
            ]);

            Message::where($whereParamsMessage)->delete();
        } catch (Exception $e) {
            dump($e->getMessage());
            (new LokiLogger())->logException($e);
        }
    }
}
