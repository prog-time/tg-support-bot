<?php

namespace Tests\Feature\Jobs;

use App\Modules\External\Jobs\SendWebhookMessage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookMessageTest extends TestCase
{
    public function test_send_message_for_user(): void
    {
        $webhookUrl = 'https://example.com/webhook';
        $payload = ['event' => 'message', 'text' => 'Hello'];

        Http::fake([
            $webhookUrl => Http::response(['ok' => true], 200),
        ]);

        $job = new SendWebhookMessage($webhookUrl, $payload);
        $job->handle();

        Http::assertSent(function ($request) use ($webhookUrl, $payload) {
            return $request->url() === $webhookUrl
                && $request->data() === $payload;
        });
    }
}
