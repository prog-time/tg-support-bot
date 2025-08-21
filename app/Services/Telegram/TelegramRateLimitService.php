<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use App\Logging\LokiLogger;

class TelegramRateLimitService
{
    /**
     * Проверяет лимит запросов для указанного метода
     *
     * @param string $methodName Название метода Telegram API
     * @param int|null $chatId ID чата (для проверки локальных лимитов)
     * @return bool true если запрос можно выполнить, false если превышен лимит
     */
    public static function checkRateLimit(string $methodName, ?int $chatId = null): bool
    {
        $logger = new LokiLogger();

        $config = config('telegram_limits.rate_limits');
        $cachePrefix = config('telegram_limits.cache.prefix');

        $currentTime = time();
        $currentSecond = $currentTime;
        $currentMinute = floor($currentTime / 60);
        $currentHour = floor($currentTime / 3600);

        // Проверяем глобальный лимит API запросов в секунду
        $globalSecondKey = "{$cachePrefix}global:second:{$currentSecond}";
        $globalRequestsPerSecond = Cache::get($globalSecondKey, 0);

        if ($globalRequestsPerSecond >= $config['global']['api_requests_per_second']) {
            $logger->log('warning', "Превышен глобальный лимит API запросов в секунду: {$globalRequestsPerSecond}");
            return false;
        }

        // Проверяем глобальный лимит сообщений в секунду (если это метод отправки сообщения)
        if (self::isMessageMethod($methodName)) {
            $globalMessagesKey = "{$cachePrefix}global:messages:second:{$currentSecond}";
            $globalMessagesPerSecond = Cache::get($globalMessagesKey, 0);

            if ($globalMessagesPerSecond >= $config['global']['messages_per_second']) {
                $logger->log('warning', "Превышен глобальный лимит сообщений в секунду: {$globalMessagesPerSecond}");
                return false;
            }
        }

        // Проверяем локальные лимиты для конкретного чата
        if ($chatId !== null) {
            if (!self::checkChatRateLimit($chatId, $methodName, $currentSecond, $cachePrefix, $config, $logger)) {
                return false;
            }
        }

        // Проверяем лимит запросов в минуту
        $minuteKey = "{$cachePrefix}minute:{$currentMinute}";
        $requestsPerMinute = Cache::get($minuteKey, 0);

        if ($requestsPerMinute >= $config['global']['requests_per_minute']) {
            $logger->log('warning', "Превышен лимит запросов в минуту: {$requestsPerMinute}");
            return false;
        }

        // Проверяем лимит запросов в час
        $hourKey = "{$cachePrefix}hour:{$currentHour}";
        $requestsPerHour = Cache::get($hourKey, 0);

        if ($requestsPerHour >= $config['global']['requests_per_hour']) {
            $logger->log('warning', "Превышен лимит запросов в час: {$requestsPerHour}");
            return false;
        }

        return true;
    }

    /**
     * Проверяет локальные лимиты для конкретного чата
     *
     * @param int $chatId ID чата
     * @param string $methodName Название метода
     * @param int $currentSecond Текущая секунда
     * @param string $cachePrefix Префикс кэша
     * @param array $config Конфигурация
     * @param LokiLogger $logger Логгер
     * @return bool
     */
    private static function checkChatRateLimit(int $chatId, string $methodName, int $currentSecond, string $cachePrefix, array $config, LokiLogger $logger): bool
    {
        // Проверяем лимит сообщений в секунду для чата
        if (self::isMessageMethod($methodName)) {
            $chatMessagesKey = "{$cachePrefix}chat:{$chatId}:messages:second:{$currentSecond}";
            $chatMessagesPerSecond = Cache::get($chatMessagesKey, 0);

            if ($chatMessagesPerSecond >= $config['per_chat']['messages_per_second']) {
                $logger->log('warning', "Превышен лимит сообщений в секунду для чата {$chatId}: {$chatMessagesPerSecond}");
                return false;
            }
        }

        // Проверяем общий лимит запросов в секунду для чата
        $chatRequestsKey = "{$cachePrefix}chat:{$chatId}:requests:second:{$currentSecond}";
        $chatRequestsPerSecond = Cache::get($chatRequestsKey, 0);

        if ($chatRequestsPerSecond >= $config['per_chat']['requests_per_second']) {
            $logger->log('warning', "Превышен лимит запросов в секунду для чата {$chatId}: {$chatRequestsPerSecond}");
            return false;
        }

        return true;
    }

    /**
     * Определяет, является ли метод методом отправки сообщения
     *
     * @param string $methodName Название метода
     * @return bool
     */
    private static function isMessageMethod(string $methodName): bool
    {
        $messageMethods = [
            'sendMessage', 'sendPhoto', 'sendVideo', 'sendAudio', 'sendVoice',
            'sendDocument', 'sendAnimation', 'sendSticker', 'sendVideoNote',
            'sendContact', 'sendLocation', 'sendVenue', 'sendPoll'
        ];

        return in_array($methodName, $messageMethods);
    }

    /**
     * Увеличивает локальные счетчики для конкретного чата
     *
     * @param int $chatId ID чата
     * @param string $methodName Название метода
     * @param int $currentSecond Текущая секунда
     * @param string $cachePrefix Префикс кэша
     * @return void
     */
    private static function incrementChatCounters(int $chatId, string $methodName, int $currentSecond, string $cachePrefix): void
    {
        // Увеличиваем счетчик сообщений в секунду для чата
        if (self::isMessageMethod($methodName)) {
            $chatMessagesKey = "{$cachePrefix}chat:{$chatId}:messages:second:{$currentSecond}";
            Cache::put($chatMessagesKey, Cache::get($chatMessagesKey, 0) + 1, 2); // TTL 2 секунды
        }

        // Увеличиваем общий счетчик запросов в секунду для чата
        $chatRequestsKey = "{$cachePrefix}chat:{$chatId}:requests:second:{$currentSecond}";
        Cache::put($chatRequestsKey, Cache::get($chatRequestsKey, 0) + 1, 2); // TTL 2 секунды
    }

    /**
     * Ожидает необходимое время для соблюдения лимитов
     *
     * @param string $methodName Название метода Telegram API
     * @return void
     */
    public static function waitForRateLimit(string $methodName): void
    {
        $config = config('telegram_limits.rate_limits');

        // Выбираем задержку в зависимости от типа метода
        if (self::isMessageMethod($methodName)) {
            $delay = $config['delays']['between_messages'];
        } else {
            $delay = $config['delays']['between_api_requests'];
        }

        // Добавляем небольшую случайность для распределения нагрузки
        $randomDelay = $delay + (rand(0, 10));

        usleep($randomDelay * 1000); // Конвертируем в микросекунды
    }
}
