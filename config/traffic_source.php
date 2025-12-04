<?php

return [
    'settings' => [
        'telegram' => [
            'token' => env('TELEGRAM_TOKEN', ''),
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_GROUP_ID', ''),
            // use IPv4 only to connect to Telegram api
            'force_ipv4' => (bool)env('TELEGRAM_FORCE_IPV4', false),
            'template_topic_name' => env('TELEGRAM_TOPIC_NAME', ''),
        ],
        'telegram_ai' => [
            'username' => env('TELEGRAM_AI_BOT_USERNAME', ''),
            'token' => env('TELEGRAM_AI_BOT_TOKEN', ''),
        ],

        'vk' => [
            'token' => env('VK_TOKEN', ''),
            'secret_key' => env('VK_SECRET_CODE', ''),
            'confirm_code' => env('VK_CONFIRM_CODE', ''),
        ],
    ],
];
