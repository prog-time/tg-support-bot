<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI services integration including API keys,
    | endpoints, and service-specific settings.
    |
    */

    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'enabled' => env('AI_ENABLED', 'false'),

    'auto_reply' => env('AI_AUTO_REPLY', 'false'),

    'disable_timeout' => env('AI_DISABLE_TIMEOUT', 'false'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        ],
        'deepseek' => [
            'client_id' => env('DEEPSEEK_CLIENT_ID'),
            'client_secret' => env('DEEPSEEK_CLIENT_SECRET'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/chat/completions'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'max_tokens' => env('DEEPSEEK_MAX_TOKENS', 1000),
            'temperature' => env('DEEPSEEK_TEMPERATURE', 0.7),
        ],
        'gigachat' => [
            'client_id' => env('GIGACHAT_CLIENT_ID'),
            'client_secret' => env('GIGACHAT_CLIENT_SECRET'),
            'base_url' => env('GIGACHAT_BASE_URL', 'https://gigachat.devices.sberbank.ru/api/v1'),
            'model' => env('GIGACHAT_MODEL', 'GigaChat-2-Max'),
            'max_tokens' => env('GIGACHAT_MAX_TOKENS', 1000),
            'temperature' => env('GIGACHAT_TEMPERATURE', 0.7),
            'path_cert' => env('GIGACHAT_CERT_PATH', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Assistant Settings
    |--------------------------------------------------------------------------
    |
    | General settings for the AI assistant functionality.
    |
    */

    'confidence_threshold' => env('AI_CONFIDENCE_THRESHOLD', 0.8),
    'max_context_messages' => env('AI_MAX_CONTEXT_MESSAGES', 10),
    'auto_escalation' => env('AI_AUTO_ESCALATION', true),
    'enable_logging' => env('AI_ENABLE_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting settings for AI API calls.
    |
    */

    'rate_limit' => [
        'requests_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 60),
        'requests_per_hour' => env('AI_RATE_LIMIT_PER_HOUR', 1000),
    ],
];
