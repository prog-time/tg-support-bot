<?php

namespace App\TelegramBot;

use App\DTOs\TelegramAnswerDto;
use App\Services\Telegram\TelegramRateLimitService;

class TelegramMethods
{
    /**
     * Отправка запроса в Telegram с проверкой лимитов
     *
     * @param string $methodQuery
     * @param ?array $dataQuery
     *
     * @return TelegramAnswerDto
     */
    public static function sendQueryTelegram(string $methodQuery, ?array $dataQuery = null, ?string $token = null): TelegramAnswerDto
    {
        try {
            // Извлекаем chat_id из данных запроса для проверки локальных лимитов
            $chatId = $dataQuery['chat_id'] ?? null;

            // Проверяем лимиты запросов
            if (!TelegramRateLimitService::checkRateLimit($methodQuery, $chatId)) {
                // Если превышен лимит, ждем необходимое время
                TelegramRateLimitService::waitForRateLimit($methodQuery);

                return TelegramAnswerDto::fromData([
                    'ok' => false,
                    'error_code' => 429,
                    'result' => 'Rate limit exceeded',
                ]);
            }

            $token = $token ?? config('traffic_source.settings.telegram.token');

            $domainQuery = 'https://api.telegram.org/bot' . $token . '/';
            $urlQuery = $domainQuery . $methodQuery;

            if (!empty($dataQuery['uploaded_file'])) {
                $resultQuery = ParserMethods::attachQuery($urlQuery, $dataQuery);
            } else {
                $resultQuery = ParserMethods::postQuery($urlQuery, $dataQuery);
            }

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
