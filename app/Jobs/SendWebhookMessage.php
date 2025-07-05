<?php

namespace App\Jobs;

use App\DTOs\Redis\WebhookMessageDto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $url;

    protected WebhookMessageDto $payload;

    public function __construct(string $url, WebhookMessageDto $payload)
    {
        $this->url = $url;
        $this->payload = $payload;
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        $response = Http::post($this->url, $this->payload->toArray());

        Log::info(json_encode([
            'status' => $response->status(),
            'body' => $response->body(),
        ]));
    }
}
