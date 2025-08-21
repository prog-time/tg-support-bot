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
     * @return TelegramAnswerDto|null
     */
    public static function execute(BotUser $botUser, TGTextMessageDto $queryParams, int $countRepeat = 1): ?TelegramAnswerDto
    {
        try {
            $countRepeat++;
            if ($countRepeat > 3) {
                throw new Exception('Максимальное количество попыток отправить сообщение!', 1);
            }

            $typeSource = $queryParams->typeSource;
            $dataQuery = $queryParams->toArray();

            $resultQuery = TelegramMethods::sendQueryTelegram($queryParams->methodQuery, $dataQuery);
            if ($resultQuery->ok === false) {
                if ($resultQuery->error_code === 400 && $resultQuery->type_error === 'markdown') {
                    $queryParams->parse_mode = 'html';
                }

                if ($resultQuery->error_code === 400 && $resultQuery->type_error === 'message is not modified') {
                    throw new Exception('Сообщение не изменено', 1);
                }

                if ($resultQuery->error_code === 400 && $resultQuery->type_error === 'message to edit not found') {
                    throw new Exception('Сообщение не найдено', 1);
                }

                if ($resultQuery->error_code === 400 && $resultQuery->type_error === 'error media') {
                    return $resultQuery;
                }

                if ($typeSource === 'private') {
                    if ($resultQuery->error_code == 400 && $resultQuery->rawData['description'] == 'Bad Request: wrong type of the web page content') {
                        throw new Exception($resultQuery->rawData['description'], 1);
                    } elseif ($resultQuery->error_code == 400 && $resultQuery->type_error !== 'markdown') {
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
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'error_code' => 500,
                'result' => $e->getCode() === 1 ? $e->getMessage() : 'Ошибка отправки запроса',
            ]);
        }
    }
}
