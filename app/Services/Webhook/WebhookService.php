<?php

namespace App\Services\Webhook;

use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;

class WebhookService
{
    /**
     * @param string $url
     * @param array  $dataMessage
     *
     * @return string|null
     */
    public function sendMessage(string $url, array $dataMessage): ?string
    {
        try {
            (new LokiLogger())->log('debug', [
                'url' => $url,
                'message' => $dataMessage,
            ]);

            $response = Http::timeout(10)->asJson()->post($url, $dataMessage);
            if ($response->failed()) {
                throw new \RuntimeException('Ошибка! Статус: ' . $response->status() . ', body: ' . $response->body());
            }

            return $response->body();
        } catch (\Throwable $e) {
            (new LokiLogger())->log('error_webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
