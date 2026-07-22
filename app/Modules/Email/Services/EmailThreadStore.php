<?php

namespace App\Modules\Email\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Per-conversation email thread state (last inbound Message-ID + Subject),
 * used to build correct `In-Reply-To` / `References` / `Subject: Re: ...`
 * headers when a reply goes out later (manager reply, AI answer, banned/start
 * notice, feedback prompt).
 *
 * There is no dedicated DB column for this (the `messages` table has no
 * `message_id` field and issue #214 explicitly rules out a schema change —
 * see the Completion Report for that decision). The cache store is the
 * pragmatic middle ground: correct threading for the common case without a
 * migration; if the cache entry expires or is evicted, replies still send
 * (with a `Re: <default subject>` and no In-Reply-To/References), they just
 * lose precise thread linkage in the recipient's mail client.
 *
 * Keyed by BotUser id, not by email address, so it lines up with how the
 * rest of the channel resolves a conversation.
 */
class EmailThreadStore
{
    private const DEFAULT_SUBJECT = 'Поддержка';

    private const TTL_DAYS = 90;

    /**
     * Remember the most recent inbound message for a conversation.
     *
     * @param int    $botUserId
     * @param string $messageId Raw Message-ID (no angle brackets); ignored when empty.
     * @param string $subject
     *
     * @return void
     */
    public function remember(int $botUserId, string $messageId, string $subject): void
    {
        if ($messageId === '') {
            return;
        }

        Cache::put($this->key($botUserId), [
            'message_id' => $messageId,
            'subject' => $subject,
        ], now()->addDays(self::TTL_DAYS));
    }

    /**
     * @param int $botUserId
     *
     * @return array{message_id: string, subject: string}|null
     */
    public function get(int $botUserId): ?array
    {
        $value = Cache::get($this->key($botUserId));

        return is_array($value) ? $value : null;
    }

    /**
     * Resolve the headers for an outgoing reply to this conversation.
     *
     * @param int $botUserId
     *
     * @return array{subject: string, inReplyTo: string|null, references: string|null}
     */
    public function replyHeaders(int $botUserId): array
    {
        $thread = $this->get($botUserId);
        $messageId = $thread['message_id'] ?? null;

        return [
            'subject' => $this->threadSubject($thread['subject'] ?? null),
            'inReplyTo' => !empty($messageId) ? $messageId : null,
            'references' => !empty($messageId) ? $messageId : null,
        ];
    }

    /**
     * @param string|null $subject
     *
     * @return string
     */
    private function threadSubject(?string $subject): string
    {
        if (empty($subject)) {
            return self::DEFAULT_SUBJECT;
        }

        return preg_match('/^re:/i', $subject) === 1 ? $subject : 'Re: ' . $subject;
    }

    /**
     * @param int $botUserId
     *
     * @return string
     */
    private function key(int $botUserId): string
    {
        return 'email:thread:' . $botUserId;
    }
}
