<?php

namespace App\TelegramBot;

use Illuminate\Support\Facades\Http;

class ParserMethods
{
    /**
     * Отправка POST запроса
     *
     * @param string       $urlQuery
     * @param array|string $queryParams
     * @param array        $queryHeading
     *
     * @return array
     */
    public static function postQuery(string $urlQuery, array|string $queryParams = [], array $queryHeading = []): array
    {
        try {
            $response = Http::withHeaders($queryHeading)->post($urlQuery, $queryParams);
            $resultQuery = $response->json();

            if (empty($resultQuery)) {
                throw new \Exception('Запрос вызвал ошибку');
            }

            return $resultQuery;
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'result' => 'Ошибка отправки запроса',
            ];
        }
    }

    /**
     * Отправка GET запроса
     *
     * @param string       $urlQuery
     * @param array|string $queryParams
     * @param array        $queryHeading
     *
     * @return array
     */
    public static function getQuery(string $urlQuery, array|string $queryParams = [], array $queryHeading = []): array
    {
        try {
            if (!empty($queryParams)) {
                $urlQuery = $urlQuery . '?' . http_build_query($queryParams);
            }

            $response = Http::withHeaders($queryHeading)->withoutVerifying()->get($urlQuery);
            $resultQuery = $response->json();

            if (empty($resultQuery)) {
                throw new \Exception('Запрос вызвал ошибку');
            }

            return $resultQuery;
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'result' => 'Ошибка отправки запроса',
            ];
        }
    }
}
