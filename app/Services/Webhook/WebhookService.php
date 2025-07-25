<?php

namespace App\Services\Webhook;

use App\DTOs\Redis\WebhookMessageDto;
use App\Jobs\SendWebhookMessage;

class WebhookService
{
    public static function sendWebhookMessage(string $webhookUrl, WebhookMessageDto $webhookMessageDto): void
    {
        if (!empty($webhookUrl)) {
            $webhookData = [
                'action' => 'send_message',
                'data' => $webhookMessageDto->toArray(),
            ];
            SendWebhookMessage::dispatch($webhookUrl, $webhookData);
        }
    }
}
