<?php

return [
    /*
     * Infrastructure / deployment settings that are NOT managed via the DB
     * settings layer. All channel credentials (tokens, secrets, identifiers)
     * have been moved to the `settings` table and are accessed exclusively
     * via App\Services\Settings\SettingsService.
     */
    'telegram' => [
        // use IPv4 only to connect to Telegram API — infra flag, stays in .env
        'force_ipv4' => (bool) env('TELEGRAM_FORCE_IPV4', false),

        // HTTP/SOCKS proxy for outgoing Telegram Bot API requests, e.g. socks5h://user:pass@host:1080
        'proxy' => env('TELEGRAM_PROXY'),
    ],
];
