<?php

namespace App\Jobs;

use App\Logging\LokiLogger;
use App\Services\Webhook\WebhookService;
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

    protected string $url;

    protected array $payload;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    public function __construct(string $url, array $payload)
    {
        $this->url = $url;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
            if (empty($this->url)) {
                throw new \Exception('Webhook URL пустой', 1);
            }

            (new WebhookService())->sendMessage($this->url, $this->payload);
        } catch (\Exception $e) {
            (new LokiLogger())->logException($e);

            $this->fail($e->getMessage());
        }
    }
}
