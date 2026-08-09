<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\WebPush\WebPushSenderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fans a new incoming message out to every subscribed admin device via Web
 * Push — delivered through the browser's push service (APNs on iOS), so it
 * reaches an installed PWA even while the device is locked or the app is
 * fully closed. Complements (does not replace) the poll-driven foreground
 * notifications in `ConversationPage`.
 */
class SendWebPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param Message $message
     */
    public function __construct(public readonly Message $message)
    {
    }

    /**
     * @return void
     */
    public function handle(WebPushSenderInterface $sender): void
    {
        $subscriptions = PushSubscription::all();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $title = 'Новое сообщение';
        $body = Str::limit((string) $this->message->text, 120);

        foreach ($subscriptions as $subscription) {
            $result = $sender->send($subscription, $title, $body);

            if ($result->expired) {
                $subscription->delete();

                continue;
            }

            if (!$result->success) {
                Log::channel('app')->warning('SendWebPushNotificationJob: delivery failed', [
                    'push_subscription_id' => $subscription->id,
                    'message_id' => $this->message->id,
                ]);
            }
        }
    }
}
