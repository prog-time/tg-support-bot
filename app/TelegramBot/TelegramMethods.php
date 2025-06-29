<?php

namespace App\TelegramBot;

use App\DTOs\TelegramAnswerDto;

class TelegramMethods
{
    /**
     * Send request in Telegram
     *
     * @param string $methodQuery
     * @param ?array $dataQuery
     *
     * @return TelegramAnswerDto
     */
    public static function sendQueryTelegram(string $methodQuery, array $dataQuery = null): TelegramAnswerDto
    {
        try {
            $token = config('traffic_source.settings.telegram.token');

            $domainQuery = 'https://api.telegram.org/bot' . $token . '/';
            $urlQuery = $domainQuery . $methodQuery;

            $resultQuery = ParserMethods::postQuery($urlQuery, $dataQuery);
            return TelegramAnswerDto::fromData($resultQuery);
        } catch (\Exception $e) {
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'error_code' => 500,
                'result' => $e->getMessage(),
            ]);
        }
    }
}
