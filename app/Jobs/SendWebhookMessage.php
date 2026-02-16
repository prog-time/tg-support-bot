<?php

namespace App\Jobs;

use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWebhookMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $url;

    public array $payload;

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
            if (empty($this->url)) {
                throw new \Exception('Webhook URL is empty', 1);
            }

            (new WebhookService())->sendMessage($this->url, $this->payload);
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            $this->fail($e->getMessage());
        }
    }
}
