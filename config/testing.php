<?php

return [
    'external' => [
        'source' => env('TEST_EXTERNAL_SOURCE', ''),
        'external_id' => env('TEST_EXTERNAL_ID', ''),
    ],
    'tg_bot_ai' => [
        'username' => env('TEST_USER_USERNAME', ''),
        'first_name' => env('TEST_USER_FIRST_NAME', ''),
        'last_name' => env('TEST_USER_LAST_NAME', ''),
    ],
    'tg_group' => [
        'chat_id' => env('TELEGRAM_GROUP_ID', ''),
    ],
    'tg_private' => [
        'chat_id' => env('TEST_USER_CHAT_ID', ''),
        'username' => env('TEST_USER_USERNAME', ''),
        'first_name' => env('TEST_USER_FIRST_NAME', ''),
        'last_name' => env('TEST_USER_LAST_NAME', ''),
    ],
    'vk_private' => [
        'chat_id' => env('TEST_VK_USER_CHAT_ID', ''),
    ],
    'tg_file' => [
        'document' => env('TEST_DOCUMENT', ''),
        'photo' => env('TEST_PHOTO', ''),
        'sticker' => env('TEST_STICKER', ''),
        'video_note' => env('TEST_VIDEO_NOTE', ''),
        'voice' => env('TEST_VOICE', ''),
    ],
];
