<?php

namespace App\TelegramBot;

use App\DTOs\TelegramAnswerDto;

class TelegramMethods
{
    /**
     * Send request to Telegram with rate limit check.
     *
     * @param string $methodQuery
     * @param ?array $dataQuery
     *
     * @return TelegramAnswerDto
     */
    public static function sendQueryTelegram(string $methodQuery, ?array $dataQuery = null, ?string $token = null): TelegramAnswerDto
    {
        try {
            $token = $token ?? config('traffic_source.settings.telegram.token');

            $domainQuery = 'https://api.telegram.org/bot' . $token . '/';
            $urlQuery = $domainQuery . $methodQuery;

            if (!empty($dataQuery['uploaded_file'])) {
                $resultQuery = ParserMethods::attachQuery($urlQuery, $dataQuery);
            } else {
                $resultQuery = ParserMethods::postQuery($urlQuery, $dataQuery);
            }

            return TelegramAnswerDto::fromData($resultQuery);
        } catch (\Throwable $e) {
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getMessage(),
            ]);
        }
    }
}
