<?php

namespace App\Logging;

use GuzzleHttp\Client;
use Throwable;

class LokiLogger
{
    protected Client $client;

    protected string $url;

    public function __construct()
    {
        $this->client = new Client();
        $this->url = config('loki_custom.url');
    }

    /**
     * @param Throwable $e
     *
     * @return void
     */
    public function sendBasicLog(Throwable $e): void
    {
        $errorMessageString = 'File: ' . $e->getFile() . '; ';
        $errorMessageString .= 'Line: ' . $e->getLine() . '; ';
        $errorMessageString .= 'Error: ' . $e->getMessage();

        $this->log('error', $errorMessageString);
    }

    /**
     * Log a message to the given channel.
     *
     * @param string $level
     * @param string $message
     *
     * @return void
     */
    public function log(string $level, mixed $message): void
    {
        $payload = [
            'streams' => [
                [
                    'stream' => [
                        'app' => config('app.name'),
                        'env' => config('app.env'),
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
