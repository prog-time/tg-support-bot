<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebhookMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $url;

    protected array $payload;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    public function __construct(string $url, array $payload)
    {
        $this->url = $url;
        $this->payload = $payload;
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($this->payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HEADER => false, // Важно: не выводить заголовки в ответ
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            Log::info('Webhook sent', [
                'status' => $httpCode,
                'response' => $response,
                'error' => $error ?: null,
            ]);

            if ($error) {
                throw new \RuntimeException("cURL error: {$error}");
            }

            if ($httpCode >= 400) {
                throw new \RuntimeException("Webhook responded with status: {$httpCode}");
            }
        } catch (\Throwable $e) {
            Log::error('Webhook delivery failed', [
                'url' => $this->url,
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);

            $this->fail($e);
        }
    }
}
