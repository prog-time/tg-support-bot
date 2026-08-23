<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\WebPush\WebPushSenderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        $title = $this->notificationTitle();
        $body = (string) $this->message->text;

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

    /**
     * "{Platform} · {sender name}" — mirrors the platform label / display name
     * shown in the chat workspace header (`conversation-page.blade.php`), so
     * the push banner identifies the conversation the same way the UI does.
     *
     * @return string
     */
    private function notificationTitle(): string
    {
        $botUser = $this->message->botUser;

        if ($botUser === null) {
            return 'Новое сообщение';
        }

        $platformLabel = match ($botUser->platform) {
            'telegram' => 'Telegram',
            'vk' => 'VK',
            'max' => 'Max',
            default => ucfirst($botUser->platform),
        };

        $senderName = $botUser->display_name ?? $botUser->username ?? (string) $botUser->chat_id;

        return "{$platformLabel} · {$senderName}";
    }
}
