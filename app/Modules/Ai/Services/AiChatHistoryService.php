<?php

declare(strict_types=1);

namespace App\Modules\Ai\Services;

use App\Models\Message;

class AiChatHistoryService
{
    /**
     * Build the conversation history for the given bot user in a form
     * suitable for an LLM `messages: [{role, content}]` payload.
     *
     * Source of truth is the `messages` table. Mapping:
     *   - `message_type=incoming`  → role `user`   (slash-commands are dropped)
     *   - `message_type=outgoing`  → role `assistant` (any outgoing record is
     *                                 treated as delivered — AI drafts only
     *                                 land in `ai_messages`, never here)
     *
     * Empty `text` is dropped on both sides. The slice is sorted by
     * `created_at` ascending, then a sliding window is applied from the
     * end so that the total approximate token count stays below
     * `config('ai.max_context_tokens')`. Token weight is estimated as
     * `floor(mb_strlen($content) / 4)` — a deliberately coarse heuristic
     * with a built-in safety buffer (no tokenizer dependency).
     *
     * To stay idempotent against the race between `SendTelegramMessageJob`
     * (which inserts the incoming user message asynchronously) and the AI
     * job, the caller may pass the user's current message via
     * `$excludeLastUserText`. If the last assembled entry has
     * `role: user` with matching `content`, it is dropped so the current
     * message is not duplicated when the provider appends it as the tail.
     *
     * @param int         $botUserId           Target `bot_users.id`
     * @param string|null $excludeLastUserText The user's current message text, if any
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function buildForBotUser(int $botUserId, ?string $excludeLastUserText = null): array
    {
        $rows = Message::query()
            ->where('bot_user_id', $botUserId)
            ->orderBy('created_at', 'asc')
            ->get(['message_type', 'text', 'created_at']);

        $mapped = [];
        foreach ($rows as $row) {
            $entry = $this->mapRow((string) $row->message_type, $row->text);
            if ($entry !== null) {
                $mapped[] = $entry;
            }
        }

        $windowed = $this->applySlidingWindow($mapped);

        if ($excludeLastUserText !== null && $windowed !== []) {
            $last = $windowed[count($windowed) - 1];
            if ($last['role'] === 'user' && $last['content'] === $excludeLastUserText) {
                array_pop($windowed);
            }
        }

        return $windowed;
    }

    /**
     * Map a `messages` row to a chat-history entry, or return null if it
     * should be dropped (slash-command, empty text, unknown type).
     *
     * @param string      $messageType Raw `message_type` column value
     * @param string|null $text        Raw `text` column value
     *
     * @return array{role: string, content: string}|null
     */
    private function mapRow(string $messageType, ?string $text): ?array
    {
        if ($text === null) {
            return null;
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        if ($messageType === 'incoming') {
            if (str_starts_with($trimmed, '/')) {
                return null;
            }

            return ['role' => 'user', 'content' => $text];
        }

        if ($messageType === 'outgoing') {
            return ['role' => 'assistant', 'content' => $text];
        }

        return null;
    }

    /**
     * Trim the entries from the start until the total estimated token
     * count fits within `ai.max_context_tokens`. Always keeps the newest
     * entries (the tail).
     *
     * @param array<int, array{role: string, content: string}> $entries
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function applySlidingWindow(array $entries): array
    {
        $limit = (int) config('ai.max_context_tokens', 3000);
        if ($limit <= 0 || $entries === []) {
            return $entries;
        }

        $kept = [];
        $total = 0;

        foreach (array_reverse($entries) as $entry) {
            $cost = $this->estimateTokens($entry['content']);
            if ($total + $cost > $limit) {
                break;
            }

            $kept[] = $entry;
            $total += $cost;
        }

        return array_reverse($kept);
    }

    /**
     * Approximate token count for a piece of content.
     *
     * Uses the rough rule of thumb that 1 token ≈ 4 characters
     * (slightly fewer for Cyrillic, so the limit is set conservatively
     * below the real model window).
     *
     * @param string $content
     *
     * @return int
     */
    private function estimateTokens(string $content): int
    {
        return (int) floor(mb_strlen($content) / 4);
    }
}
