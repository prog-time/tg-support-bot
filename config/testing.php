<?php

return [
    'tg_private' => [
        'chat_id' => env('TEST_USER_CHAT_ID', ''),
        'username' => env('TEST_USER_USERNAME', ''),
        'first_name' => env('TEST_USER_FIRST_NAME', ''),
        'last_name' => env('TEST_USER_LAST_NAME', ''),
    ],
    'vk_private' => [
        'chat_id' => env('TEST_VK_USER_CHAT_ID', ''),
    ],
];
