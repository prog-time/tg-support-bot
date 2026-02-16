<?php

namespace App\Logging;

use GuzzleHttp\Client;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

class LokiHandler extends AbstractProcessingHandler
{
    protected Client $client;

    public function __construct(
        private string $url,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
        $this->client = new Client();
    }

    /**
     * Write a log record to Loki.
     *
     * @param LogRecord $record The log record to write
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $labels = [
            'app' => config('app.name', 'laravel'),
            'env' => config('app.env', 'production'),
            'level' => strtolower($record->level->name),
        ];

        foreach ($record->context as $key => $value) {
            if (is_string($value)) {
                $labels[$key] = $value;
            }
        }

        $payload = [
            'streams' => [
                [
                    'stream' => $labels,
                    'values' => [
                        [
                            (string) (int) (microtime(true) * 1e9),
                            $record->message,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->client->post($this->url, ['json' => $payload]);
        } catch (Throwable) {
            // Silent fail — logging must not break the application
        }
    }
}
