<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;

class SendMessage
{
    /**
     * @param BotUser $botUser
     * @param TGTextMessageDto $queryParams
     * @return TelegramAnswerDto|null
     */
    public static function execute(BotUser $botUser, TGTextMessageDto $queryParams): ?TelegramAnswerDto
    {
        try {
            $typeSource = $queryParams->typeSource;
            $dataQuery = $queryParams->toArray();

            $resultQuery = TelegramMethods::sendQueryTelegram($queryParams->methodQuery, $dataQuery);
            if ($resultQuery->ok === false) {
                if ($typeSource === 'private') {
                    switch ($resultQuery->error_code) {
                        case 400:
                            $messageThreadId = $botUser->saveNewTopic();
                            $dataQuery['message_thread_id'] = $messageThreadId;
                            $resultQuery = TelegramMethods::sendQueryTelegram($queryParams->methodQuery, $dataQuery);
                            break;

                        default:
                            die();
                    }
                } else {
                    if ($resultQuery->error_code == 403) {
                        BanMessage::execute($botUser->topic_id);
                    }
                    die();
                }
            }
            return $resultQuery;
        } catch (\Exception $e) {
            return null;
        }
    }

}
