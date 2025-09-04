<?php

return [
    'settings' => [
        'telegram' => [
            'token' => env('TELEGRAM_TOKEN', ''),
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_GROUP_ID', ''),

            'template_topic_name' => env('TELEGRAM_TOPIC_NAME', ''),
        ],
        'telegram_ai' => [
            'token' => env('TELEGRAM_AI_BOT_TOKEN', ''),
        ],

        'vk' => [
            'token' => env('VK_TOKEN', ''),
            'secret_key' => env('VK_SECRET_CODE', ''),
            'confirm_code' => env('VK_CONFIRM_CODE', ''),
        ],
    ],
];
