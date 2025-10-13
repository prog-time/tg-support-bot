<?php

namespace App\Jobs;

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
                throw new \Exception('Webhook URL is empty');
            }

            (new WebhookService())->sendMessage($this->url, $this->payload);
        } catch (\Exception $exception) {
            $this->fail($exception->getMessage());
        }
    }
}
