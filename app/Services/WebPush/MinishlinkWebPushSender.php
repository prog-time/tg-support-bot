<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use App\Services\Settings\SettingsService;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Default {@see WebPushSenderInterface} implementation, backed by the
 * `minishlink/web-push` VAPID client.
 */
class MinishlinkWebPushSender implements WebPushSenderInterface
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @param PushSubscription $subscription
     * @param string           $title
     * @param string           $body
     *
     * @return WebPushSendResult
     */
    public function send(PushSubscription $subscription, string $title, string $body): WebPushSendResult
    {
        $publicKey = (string) $this->settings->get('webpush.vapid_public_key');
        $privateKey = (string) $this->settings->get('webpush.vapid_private_key');
        $subject = (string) $this->settings->get('webpush.vapid_subject');

        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return new WebPushSendResult(success: false, expired: false);
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $report = $webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->public_key,
                    'auth' => $subscription->auth_token,
                ],
            ]),
            json_encode(['title' => $title, 'body' => $body], JSON_UNESCAPED_UNICODE)
        );

        return new WebPushSendResult(
            success: $report->isSuccess(),
            expired: $report->isSubscriptionExpired(),
        );
    }
}
