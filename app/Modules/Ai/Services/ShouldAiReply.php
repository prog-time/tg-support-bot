<?php

namespace App\Modules\Ai\Services;

use App\Models\BotUser;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Support\Facades\Log;

class ShouldAiReply
{
    /**
     * Determine whether AI should generate a reply for an incoming user message
     * processed by the main Telegram bot.
     *
     * Rules (all must pass):
     * 1. AI is globally enabled (AI_ENABLED=true).
     * 2. Manager interface is telegram_group (admin_panel does not use AI here).
     * 3. Message arrived from a private chat with the main bot.
     * 4. Update type is a regular message (not callback, edited, etc.).
     * 5. Text is non-empty and not a slash-command (/start, /contact, etc.).
     * 6. Bot user exists, is not banned, is not closed.
     *
     * @param TelegramUpdateDto $update
     * @param BotUser|null      $botUser
     *
     * @return bool
     */
    public function shouldGenerateForUserMessage(TelegramUpdateDto $update, ?BotUser $botUser): bool
    {
        if (!$this->isAiEnabled()) {
            $this->logSkip('ai_disabled', $update, $botUser);
            return false;
        }

        if (!$this->isTelegramGroupInterface()) {
            $this->logSkip('manager_interface_not_telegram_group', $update, $botUser, [
                'manager_interface' => config('app.manager_interface'),
            ]);
            return false;
        }

        if ($update->typeSource !== 'private') {
            $this->logSkip('not_private_chat', $update, $botUser, [
                'type_source' => $update->typeSource,
            ]);
            return false;
        }

        if ($update->typeQuery !== 'message') {
            $this->logSkip('not_message_query', $update, $botUser, [
                'type_query' => $update->typeQuery,
            ]);
            return false;
        }

        if (!$this->isReplyableText($update->text)) {
            $this->logSkip('empty_or_command_text', $update, $botUser, [
                'text_preview' => $update->text === null ? null : mb_substr($update->text, 0, 50),
            ]);
            return false;
        }

        if (!$this->isUserActive($botUser)) {
            $this->logSkip('user_inactive', $update, $botUser, [
                'bot_user_found' => $botUser !== null,
                'is_banned' => $botUser?->isBanned(),
                'is_closed' => $botUser?->isClosed(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether AI should generate a reply for an incoming message
     * received from a non-Telegram source (VK, Max, etc.).
     *
     * Reuses the AI-enabled, manager-interface, replyable-text and user-active
     * checks from the Telegram path, but does not depend on a TelegramUpdateDto.
     *
     * @param BotUser|null $botUser
     * @param string|null  $text
     *
     * @return bool
     */
    public function shouldGenerateForBotUserText(?BotUser $botUser, ?string $text): bool
    {
        if (!$this->isAiEnabled()) {
            $this->logExternalSkip('ai_disabled', $botUser);
            return false;
        }

        if (!$this->isTelegramGroupInterface()) {
            $this->logExternalSkip('manager_interface_not_telegram_group', $botUser, [
                'manager_interface' => config('app.manager_interface'),
            ]);
            return false;
        }

        if (!$this->isReplyableText($text)) {
            $this->logExternalSkip('empty_or_command_text', $botUser, [
                'text_preview' => $text === null ? null : mb_substr($text, 0, 50),
            ]);
            return false;
        }

        if (!$this->isUserActive($botUser)) {
            $this->logExternalSkip('user_inactive', $botUser, [
                'bot_user_found' => $botUser !== null,
                'is_banned' => $botUser?->isBanned(),
                'is_closed' => $botUser?->isClosed(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isAiEnabled(): bool
    {
        return (bool) config('ai.enabled', false);
    }

    /**
     * @return bool
     */
    public function isTelegramGroupInterface(): bool
    {
        return config('app.manager_interface') === 'telegram_group';
    }

    /**
     * Text must be non-empty and not a slash-command.
     *
     * @param string|null $text
     *
     * @return bool
     */
    public function isReplyableText(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        $trimmed = trim($text);
        if ($trimmed === '') {
            return false;
        }

        return !str_starts_with($trimmed, '/');
    }

    /**
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

    /**
     * Log the reason AI generation was skipped.
     *
     * @param string            $reason
     * @param TelegramUpdateDto $update
     * @param BotUser|null      $botUser
     * @param array             $extra
     *
     * @return void
     */
    private function logSkip(string $reason, TelegramUpdateDto $update, ?BotUser $botUser, array $extra = []): void
    {
        Log::channel('loki')->info("ShouldAiReply: skipped [{$reason}]", array_merge([
            'source' => 'ai_should_reply_skipped',
            'reason' => $reason,
            'bot_user_id' => $botUser?->id,
            'chat_id' => $update->chatId,
            'message_thread_id' => $update->messageThreadId,
        ], $extra));
    }

    /**
     * Log the reason AI generation was skipped for an external (non-TG) update.
     *
     * @param string       $reason
     * @param BotUser|null $botUser
     * @param array        $extra
     *
     * @return void
     */
    private function logExternalSkip(string $reason, ?BotUser $botUser, array $extra = []): void
    {
        Log::channel('loki')->info("ShouldAiReply: skipped [{$reason}]", array_merge([
            'source' => 'ai_should_reply_skipped',
            'reason' => $reason,
            'bot_user_id' => $botUser?->id,
            'platform' => $botUser?->platform,
            'topic_id' => $botUser?->topic_id,
        ], $extra));
    }
}
