<?php

namespace App\Observers;

use App\Jobs\SendWebPushNotificationJob;
use App\Models\Message;

/**
 * Dispatches Web Push delivery for every new incoming message.
 *
 * Incoming messages are written from ~10 separate platform-specific places
 * (Telegram, VK, Max, Avito, Email, External sources). An Observer on the
 * shared `Message` model is the single point that covers all of them without
 * a dispatch call duplicated into each platform service.
 */
class MessageObserver
{
    /**
     * @param Message $message
     *
     * @return void
     */
    public function created(Message $message): void
    {
        if ($message->message_type !== 'incoming') {
            return;
        }

        dispatch(new SendWebPushNotificationJob($message));
    }
}
