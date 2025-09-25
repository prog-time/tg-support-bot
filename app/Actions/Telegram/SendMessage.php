<?php

namespace App\Actions\Telegram;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\Models\BotUser;
use App\TelegramBot\TelegramMethods;
use phpDocumentor\Reflection\Exception;

/**
 * Отправка сообщения
 */
class SendMessage
{
    /**
     * Отправка сообщения
     *
     * @param BotUser          $botUser
     * @param TGTextMessageDto $queryParams
     * @param int              $countRepeat
     *
     * @return TelegramAnswerDto
     */
    public static function execute(BotUser $botUser, TGTextMessageDto $queryParams, int $countRepeat = 1): TelegramAnswerDto
    {
        try {
            $countRepeat++;
            if ($countRepeat > 3) {
                throw new Exception('Максимальное количество попыток отправить сообщение!', 1);
            }

            $dataQuery = $queryParams->toArray();

            $resultQuery = TelegramMethods::sendQueryTelegram($queryParams->methodQuery, $dataQuery, $queryParams->token);
            if ($resultQuery->ok === false) {
                if ($resultQuery->response_code === 400) {
                    switch ($resultQuery->type_error) {
                        case 'MARKDOWN_ERROR':
                            $queryParams->parse_mode = 'html';
                            $resultQuery = self::execute($botUser, $queryParams, $countRepeat);
                            break;

                        case 'TOPIC_NOT_FOUND':
                            $messageThreadId = $botUser->saveNewTopic();
                            $queryParams->message_thread_id = $messageThreadId;
                            $resultQuery = self::execute($botUser, $queryParams, $countRepeat);
                            break;
                    }
                } elseif ($resultQuery->response_code === 403) {
                    BanMessage::execute($botUser->topic_id);
                    die();
                }
            }

            return $resultQuery;
        } catch (\Exception $e) {
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка отправки запроса',
            ]);
        }
    }
}
