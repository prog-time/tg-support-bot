<?php

namespace App\Logging;

use GuzzleHttp\Client;
use Throwable;

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
     * @param Throwable $e
     * @return void
     */
    public function sendBasicLog(Throwable $e): void
    {
        $request = request();

        $errorMessageString = 'File: ' . $e->getFile() . '; ';
        $errorMessageString .= 'Line: ' . $e->getLine() . '; ';
        $errorMessageString .= 'Error: ' . $e->getMessage();

        $this->log('error', $errorMessageString, [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => $request->all(),
        ]);
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

        $this->client->post($this->url, [
            'json' => $payload,
        ]);
    }
}
