<?php

namespace App\Services\Webhook;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            $response = Http::timeout(10)->asJson()->post($url, $dataMessage);
            if ($response->failed()) {
                throw new \RuntimeException('Ошибка! Статус: ' . $response->status() . ', body: ' . $response->body());
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            return null;
        }
    }
}
