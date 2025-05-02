<?php

namespace App\Logging;

use GuzzleHttp\Client;

class LokiLogger
{
    protected $client;
    protected $url;

    public function __construct()
    {
        $this->client = new Client();
        $this->url = env('LOKI_URL_PUSH', 'http://loki:3100/loki/api/v1/push');
    }

    /**
     * Log a message to the given channel.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    public function log(string $level, mixed $message, array $context = []): void
    {
        $payload = [
            'streams' => [
                [
                    'stream' => [
                        'app' => env('APP_NAME', 'Laravel'),
                        'env' => env('APP_ENV', 'production'),
                        'level' => $level,
                    ],
                    'values' => [
                        [
                            (string) (int) (microtime(true) * 1e9),
                            $message,
                        ],
                    ],
                ],
            ],
        ];

//        $this->client->post($this->url, [
//            'json' => $payload,
//        ]);
    }
}
