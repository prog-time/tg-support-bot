<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Server
    |--------------------------------------------------------------------------
    | Core-level licensing config used by the «Подписки» screen to resolve which
    | paid modules a single license key grants. Independent of any individual
    | module (e.g. the Avito package), so the subscriptions screen works even
    | when no licensed module is installed.
    |
    | The list endpoint mirrors the per-module verify endpoint: it returns a
    | signed (Ed25519) token whose payload lists the products for the key. The
    | signature is verified here with the public key below — a spoofed server
    | cannot forge the list.
    */

    'server_url' => env('LICENSE_SERVER_URL', 'https://licenses.iliya-code.ru'),

    // Server's Ed25519 PUBLIC key (base64). Safe to ship — used only to verify
    // signatures. Matches the key embedded in the paid modules.
    'public_key' => env('LICENSE_PUBLIC_KEY', 'gLORt3/YOoZvUB7nKGTCdxst53yMHi14oeVXaY6BQ1M='),

    // HTTP timeout for the license list call (seconds).
    'http_timeout' => (int) env('LICENSE_HTTP_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Licensed module setting keys
    |--------------------------------------------------------------------------
    | The shared key entered on the «Подписки» screen is stored under
    | `license.key` and mirrored into each installed module's own license
    | setting key, because every module's LicenseGuard reads its own key (e.g.
    | the Avito module reads `avito.license_key`). Map: product slug → setting key.
    */

    'module_keys' => [
        'avito' => 'avito.license_key',
    ],

];
