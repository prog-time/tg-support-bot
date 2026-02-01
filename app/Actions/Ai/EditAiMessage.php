<?php

namespace App\Actions\Ai;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\TGTextMessageDto;
use App\Helpers\AiHelper;
use App\Jobs\SendMessage\SendTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\AiMessage;
use App\Models\BotUser;
use phpDocumentor\Reflection\Exception;

class EditAiMessage
{
    /**
     * Edit AI message.
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
                throw new \Exception('Bot user not found');
            }

            $updateText = $update->text;
            if (empty($updateText)) {
                throw new Exception('Message text not found!', 1);
            }

            preg_match('/ai_message_edit_[0-9]+/', $updateText, $matches);

            if (empty($matches[0])) {
                throw new Exception('Command not found in text!', 1);
            }

            $messageParams = explode('_', $matches[0]);
            if (empty($messageParams[3])) {
                throw new Exception('Message ID not found!', 1);
            }

            $messageId = $messageParams[3];
            if (!is_numeric($messageId)) {
                throw new Exception('Message ID is not a number!', 1);
            }

            $messageData = AiMessage::where('message_id', $messageId)->first();
            if (empty($messageData)) {
                throw new Exception('Message not found in database!', 1);
            }

            $newTextMessage = preg_replace('/^.*\R/', '', $updateText, 1);
            $textMessage = AiHelper::preparedAiAnswer($messageData->text_manager, $newTextMessage);

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
                    'text' => $textMessage,
                    'parse_mode' => 'html',
                    'reply_markup' => AiHelper::preparedAiReplyMarkup((int)$messageData->message_id, $newTextMessage),
                ]),
                'incoming',
            );

            AiMessage::where('message_id', $messageId)->update([
                'text_ai' => $newTextMessage,
            ]);

            SendTelegramMessageJob::dispatch(
                $botUser->id,
                $update,
                TGTextMessageDto::from([
                    'methodQuery' => 'deleteMessage',
                    'chat_id' => $update->chatId,
                    'message_id' => $update->messageId,
                ]),
                'incoming',
            );
        } catch (\Throwable $e) {
            (new LokiLogger())->log('ai_error', $e->getMessage());
        }
    }
}
