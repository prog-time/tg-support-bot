<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram API Rate Limits Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки лимитов запросов для Telegram Bot API
    | Документация: https://core.telegram.org/bots/faq#my-bot-is-hitting-limits-how-do-i-avoid-this
    |
    */

    'rate_limits' => [
        // Глобальные лимиты для всего бота
        'global' => [
            // Максимальное количество сообщений в секунду (суммарно по всем чатам)
            'messages_per_second' => env('TELEGRAM_MESSAGES_PER_SECOND', 30),
            
            // Максимальное количество запросов API в секунду (включая все методы)
            'api_requests_per_second' => env('TELEGRAM_API_REQUESTS_PER_SECOND', 50),
            
            // Максимальное количество запросов в минуту
            'requests_per_minute' => env('TELEGRAM_REQUESTS_PER_MINUTE', 100),
            
            // Максимальное количество запросов в час
            'requests_per_hour' => env('TELEGRAM_REQUESTS_PER_HOUR', 1000),
        ],
        
        // Локальные лимиты для конкретного чата
        'per_chat' => [
            // Максимальное количество сообщений в секунду для одного чата
            'messages_per_second' => env('TELEGRAM_PER_CHAT_MESSAGES_PER_SECOND', 1),
            
            // Максимальное количество запросов в секунду для одного чата
            'requests_per_second' => env('TELEGRAM_PER_CHAT_REQUESTS_PER_SECOND', 5),
        ],
        
        // Задержки и ожидания
        'delays' => [
            // Время задержки между сообщениями в миллисекундах
            'between_messages' => env('TELEGRAM_DELAY_BETWEEN_MESSAGES', 33),
            
            // Время задержки между API запросами в миллисекундах
            'between_api_requests' => env('TELEGRAM_DELAY_BETWEEN_API_REQUESTS', 20),
            
            // Время задержки при получении ошибки 429 (Too Many Requests)
            'retry_after_429' => env('TELEGRAM_RETRY_AFTER_429', 60),
        ],
        
        // Настройки повторов
        'retry' => [
            // Максимальное количество попыток повтора при ошибке 429
            'max_attempts' => env('TELEGRAM_MAX_RETRY_ATTEMPTS', 3),
            
            // Экспоненциальная задержка между попытками (множитель)
            'backoff_multiplier' => env('TELEGRAM_RETRY_BACKOFF_MULTIPLIER', 2),
        ],
    ],

    'cache' => [
        // Префикс для ключей кэша
        'prefix' => 'telegram_rate_limit:',
        
        // Время жизни кэша в секундах
        'ttl' => 3600,
    ],
];
