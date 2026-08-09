<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;

/**
 * Sends a single Web Push notification to a subscribed browser/device.
 *
 * Wraps the vendor client (`minishlink/web-push`, which makes its own cURL
 * calls outside Laravel's `Http` facade) behind an interface so it can be
 * swapped for a fake in tests — mirrors `App\Modules\Ai\Contracts\AiProviderInterface`.
 */
interface WebPushSenderInterface
{
    /**
     * @param PushSubscription $subscription
     * @param string           $title
     * @param string           $body
     *
     * @return WebPushSendResult
     */
    public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult;
}
