<?php

namespace App\Modules\Ai\Services;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;

class ShouldAiReply
{
    /**
     * Determine whether the AI bot should reply to the given update.
     *
     * Rules (all must pass):
     * 1. AI is globally enabled (AI_ENABLED=true).
     * 2. The message arrived in a supergroup topic (chat.type=supergroup, message_thread_id present).
     * 3. The sender is the main Telegram bot (from.id === TELEGRAM_BOT_ID), i.e. a forwarded user message.
     * 4. The bot user exists, is not banned, and is not closed.
     *
     * @param TelegramUpdateDto $update
     * @param BotUser|null      $botUser
     *
     * @return bool
     */
    public function shouldReply(TelegramUpdateDto $update, ?BotUser $botUser): bool
    {
        // Rule 1: AI must be globally enabled
        if (!$this->isAiEnabled()) {
            return false;
        }

        // Rule 2: Message must be from within a supergroup topic
        if (!$this->isSupergroupTopic($update)) {
            return false;
        }

        // Rule 3: Sender must be the main bot (forwarded user message), not manager, not AI bot itself
        if (!$this->isFromMainBot($update)) {
            return false;
        }

        // Rule 4: Bot user must exist and not be banned
        if (!$this->isUserActive($botUser)) {
            return false;
        }

        return true;
    }

    /**
     * Check if AI is globally enabled.
     *
     * @return bool
     */
    public function isAiEnabled(): bool
    {
        return (bool) config('ai.enabled', false);
    }

    /**
     * Check if the update originates from a supergroup forum topic.
     *
     * @param TelegramUpdateDto $update
     *
     * @return bool
     */
    public function isSupergroupTopic(TelegramUpdateDto $update): bool
    {
        return $update->typeSource === 'supergroup'
            && $update->messageThreadId !== null;
    }

    /**
     * Check if the message sender is the main Telegram bot.
     *
     * Comparing from.id to TELEGRAM_BOT_ID identifies forwarded user messages
     * and prevents the AI bot from responding to manager messages or its own messages.
     *
     * @param TelegramUpdateDto $update
     *
     * @return bool
     */
    public function isFromMainBot(TelegramUpdateDto $update): bool
    {
        $mainBotId = (int) config('traffic_source.settings.telegram.bot_id', 0);
        if ($mainBotId === 0) {
            return false;
        }

        $rawData = $update->rawData;
        $fromId = (int) ($rawData['message']['from']['id'] ?? 0);

        return $fromId === $mainBotId;
    }

    /**
     * Check if the bot user is active (exists, not banned, not closed).
     *
     * @param BotUser|null $botUser
     *
     * @return bool
     */
    public function isUserActive(?BotUser $botUser): bool
    {
        if ($botUser === null) {
            return false;
        }

        return !$botUser->isBanned() && !$botUser->isClosed();
    }
}
