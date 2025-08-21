<?php

return [
    'settings' => [
        'telegram' => [
            'token' => env('TELEGRAM_TOKEN', ''),
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_GROUP_ID', ''),

            'template_topic_name' => env('TELEGRAM_TOPIC_NAME', ''),

            // Ограничения частоты запросов (Rate Limit) для Telegram API
            'rate_limit' => [
                // Глобальный лимит запросов в секунду (примерно, Telegram допускает ~30 rps)
                'global_per_second' => env('TELEGRAM_RATE_GLOBAL_PER_SECOND', 25),
                // Лимит запросов в секунду на один чат (в среднем ~1 rps на чат)
                'per_chat_per_second' => env('TELEGRAM_RATE_PER_CHAT_PER_SECOND', 1),
            ],
        ],
        'vk' => [
            'token' => env('VK_TOKEN', ''),
            'secret_key' => env('VK_SECRET_CODE', ''),
            'confirm_code' => env('VK_CONFIRM_CODE', ''),
        ],
    ],
];
