<?php

namespace App\Services;

use App\Actions\Telegram\DeleteMessage;
use App\DTOs\TelegramTopicDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;

class TgTopicService
{

    /**
     * Create new topic
     * @param BotUser $botUser
     * @return TelegramTopicDto|null
     */
    public function createNewTgTopic(BotUser $botUser): ?TelegramTopicDto
    {
        try {
            $resultQuery = TelegramMethods::sendQueryTelegram('createForumTopic', [
                'chat_id' => env('TELEGRAM_GROUP_ID'),
                'name' => '#' . $botUser->chat_id,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]);

            if (!$resultQuery->ok) {
                throw new \Exception();
            }

            return TelegramTopicDto::from($resultQuery->rawData['result']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Edit topic
     * @param TelegramTopicDto $telegramTopicDto
     * @return TelegramTopicDto|null
     */
    public function editTgTopic(TelegramTopicDto $telegramTopicDto): ?TelegramTopicDto
    {
        try {
            $queryParams = array_merge(
                [
                    'chat_id' => env('TELEGRAM_GROUP_ID')
                ],
                $telegramTopicDto->toArray()
            );

            $resultQuery = TelegramMethods::sendQueryTelegram('editForumTopic', $queryParams);
            if (!$resultQuery->ok) {
                throw new \Exception('Error sending query');
            }

            return TelegramTopicDto::from($resultQuery->rawData['result']);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete system message
     * @param int $messageId
     * @return void
     */
    public static function deleteNoteInTopic(int $messageId): void
    {
        $messageParamsDTO = TGTextMessageDto::from([
            'methodQuery' => 'deleteMessage',
            'chat_id' => env('TELEGRAM_GROUP_ID'),
            'message_id' => $messageId,
        ]);
        DeleteMessage::execute($messageParamsDTO);
    }

}
