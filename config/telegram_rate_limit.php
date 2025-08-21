<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Простые настройки для ограничения частоты запросов к Telegram Bot API
    |
    */

    'enabled' => env('TELEGRAM_RATE_LIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits per Chat Type
    |--------------------------------------------------------------------------
    |
    | Лимиты запросов для разных типов чатов
    |
    */
    'limits' => [
        'private' => [
            'per_second' => env('TELEGRAM_RATE_LIMIT_PRIVATE_PER_SECOND', 1), // 1 запрос в секунду
            'per_minute' => env('TELEGRAM_RATE_LIMIT_PRIVATE_PER_MINUTE', 1), // 20 запросов в минуту
        ],
        'group' => [
            'per_second' => env('TELEGRAM_RATE_LIMIT_GROUP_PER_SECOND', 1), // 1 запрос в секунду
            'per_minute' => env('TELEGRAM_RATE_LIMIT_GROUP_PER_MINUTE', 30), // 30 запросов в минуту
        ],
        'supergroup' => [
            'per_second' => env('TELEGRAM_RATE_LIMIT_SUPERGROUP_PER_SECOND', 1), // 1 запрос в секунду
            'per_minute' => env('TELEGRAM_RATE_LIMIT_SUPERGROUP_PER_MINUTE', 30), // 30 запросов в минуту
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки Redis для хранения счетчиков rate limit
    |
    */
    'redis' => [
        'prefix' => env('TELEGRAM_RATE_LIMIT_REDIS_PREFIX', 'tg_rate_limit:'),
        'ttl' => [
            'second' => env('TELEGRAM_RATE_LIMIT_REDIS_TTL_SECOND', 2), // TTL для ключей секунды
            'minute' => env('TELEGRAM_RATE_LIMIT_REDIS_TTL_MINUTE', 70), // TTL для ключей минуты
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки логирования для rate limiting
    |
    */
    'logging' => [
        'enabled' => env('TELEGRAM_RATE_LIMIT_LOGGING', true),
        'level' => env('TELEGRAM_RATE_LIMIT_LOG_LEVEL', 'warning'),
    ],
];
