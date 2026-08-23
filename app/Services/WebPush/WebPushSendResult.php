<?php

namespace App\Services\WebPush;

/**
 * Outcome of a single Web Push send attempt.
 */
final class WebPushSendResult
{
    /**
     * @param bool $success Whether the push service accepted the notification.
     * @param bool $expired Whether the subscription is gone/expired and should be deleted.
     */
    public function __construct(
        public readonly bool $success,
        public readonly bool $expired,
    ) {
    }
}
