<?php

namespace App\Services\Webhook;

use App\DTOs\Redis\WebhookMessageDto;
use App\Jobs\SendWebhookMessage;

class WebhookService
{
    /**
     * @param string            $webhookUrl
     * @param WebhookMessageDto $webhookMessageDto
     *
     * @return void
     */
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
