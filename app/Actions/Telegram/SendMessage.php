<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

class SendMessage
{
    /**
     * @param BotUser $botUser
     * @param TGTextMessageDto $queryParams
     * @param int $countQuery
     * @return TelegramAnswerDto|null
     */
    public static function execute(BotUser $botUser, TGTextMessageDto $queryParams, int $countRepeat = 1): ?TelegramAnswerDto
    {
        try {
            $countRepeat++;
            if ($countRepeat > 3) {
                throw new Exception('Maximum number of messages reached');
            }

            $typeSource = $queryParams->typeSource;
            $dataQuery = $queryParams->toArray();

            $resultQuery = TelegramMethods::sendQueryTelegram($queryParams->methodQuery, $dataQuery);
            if ($resultQuery->ok === false) {
                if ($resultQuery->error_code === 400 && $resultQuery->type_error === 'markdown') {
                    $queryParams->parse_mode = 'html';
                }

                 if ($typeSource === 'private') {
                     if ($resultQuery->error_code == 400 && $resultQuery->type_error !== 'markdown') {
                         $messageThreadId = $botUser->saveNewTopic();
                         $queryParams->message_thread_id = $messageThreadId;
                     }
                } else {
                    if ($resultQuery->error_code == 403) {
                        BanMessage::execute($botUser->topic_id);
                        die();
                    }
                }
                $resultQuery = self::execute($botUser, $queryParams, $countRepeat);
            }
            return $resultQuery;
        } catch (\Exception $e) {
            return null;
        }
    }

}
