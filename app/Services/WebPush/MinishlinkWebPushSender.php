<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;
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
     * A PSR-3 logger is passed to `WebPush` because, without one, its
     * constructor reports a missing GMP/BCMath extension via
     * `trigger_error(E_USER_NOTICE)` — this container has neither installed,
     * and Laravel's error handler converts that notice into a thrown
     * `ErrorException`, aborting every send before `sendOneNotification()`
     * ever runs; the logger makes the library log the notice instead.
     *
     * The whole call is wrapped in try/catch because an uncaught exception
     * here (e.g. a cURL/TLS failure reaching the push service) must not
     * bubble up into the webhook request that saved the message — logging
     * plus a failed result keeps message delivery unaffected by push
     * delivery issues.
     *
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

        try {
            $webPush = new WebPush(
                auth: [
                    'VAPID' => [
                        'subject' => $subject,
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ],
                logger: Log::channel('app'),
            );

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
        } catch (\Throwable $e) {
            Log::channel('app')->error($e->getMessage(), [
                'push_subscription_id' => $subscription->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return new WebPushSendResult(success: false, expired: false);
        }

        return new WebPushSendResult(
            success: $report->isSuccess(),
            expired: $report->isSubscriptionExpired(),
        );
    }
}
