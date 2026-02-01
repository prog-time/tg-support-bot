<?php

namespace App\Actions\Ai;

use App\DTOs\TelegramUpdateDto;
use App\Models\AiMessage;
use phpDocumentor\Reflection\Exception;

abstract class AiAction
{
    /**
     * @param string $callbackData
     *
     * @return AiMessage|null
     */
    public function getMessageDataByCallbackData(string $callbackData): ?AiMessage
    {
        try {
            $messageParams = explode('_', $callbackData);
            if (empty($messageParams[3])) {
                throw new Exception('Message ID not found!', 1);
            }

            $messageId = $messageParams[3];
            $messageData = AiMessage::where('message_id', $messageId)->first();
            if (empty($messageData)) {
                throw new Exception('Message not found in database!', 1);
            }

            return $messageData;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @param TelegramUpdateDto $update
     *
     * @return void
     */
    abstract public function execute(TelegramUpdateDto $update): void;
}
