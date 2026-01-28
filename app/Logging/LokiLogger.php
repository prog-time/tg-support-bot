<?php

namespace App\Logging;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class LokiLogger
{
    protected Client $client;

    protected string $url;

    public function __construct(Client|null $client = null)
    {
        $this->client = $client ?? new Client();
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
     * @param mixed  $message
     *
     * @return bool
     */
    public function log(string $level, mixed $message): bool
    {
        try {
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
                                is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE),
                            ],
                        ],
                    ],
                ],
            ];

            $this->client->post($this->url, [
                'json' => $payload,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning($e->getMessage());
            return false;
        }
    }

    /**
     * @param Throwable|Exception $e
     *
     * @return bool
     */
    public function logException(Throwable|Exception $e): bool
    {
        try {
            $level = $e->getCode() === 1 ? 'warning' : 'error';

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
                                json_encode([
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                    'message' => $e->getMessage(),
                                ]),
                            ],
                        ],
                    ],
                ],
            ];

            $this->client->post($this->url, [
                'json' => $payload,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning($e->getMessage());
            return false;
        }
    }
}
