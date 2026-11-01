<?php

namespace App\Console\Commands;

use App\Models\AiMessage;
use App\Models\Setting;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Modules\Telegram\DTOs\TelegramAnswerDto;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

/**
 * One-off upgrade step that retires the former separate AI bot.
 *
 * Only the author can edit a message, so old drafts are cleaned up with the
 * old token, which is read from the Setting model directly: the removed key
 * is unknown to SettingKeyRegistry, and SettingsService would return it encrypted.
 */
class AiBotRetire extends Command
{
    private const LEGACY_KEYS = [
        'telegram_ai.token',
        'telegram_ai.secret',
        'telegram_ai.username',
        'telegram_ai.id',
    ];

    protected $signature = 'ai-bot:retire';

    protected $description = 'Clean up drafts of the former AI bot, remove its webhook and settings';

    /**
     * Run the idempotent retirement. A network failure stops it with the
     * settings intact, so a repeated run continues where this one ended.
     *
     * @param SettingsService $settings
     *
     * @return int
     */
    public function handle(SettingsService $settings): int
    {
        $token = $this->legacyToken();
        $groupId = (string) $settings->get('telegram.group_id');
        $released = 0;

        $drafts = AiMessage::where('status', AiMessage::STATUS_PENDING)->whereNotNull('message_id')->get();

        foreach ($drafts as $draft) {
            if ($token !== null && $groupId !== '') {
                $result = $this->removeDraftFromTopic($draft, $groupId, $token);

                if ($result === 'unavailable') {
                    $this->error('Telegram is unavailable. The AI bot settings were kept, run the command again.');

                    return Command::FAILURE;
                }

                if ($result === 'revoked') {
                    $token = null;
                }
            }

            $draft->update(['message_id' => null]);
            $released++;
        }

        if ($token !== null) {
            $response = TelegramMethods::sendQueryTelegram('deleteWebhook', ['drop_pending_updates' => true], $token);

            if ($response->ok !== true && $response->response_code !== 401) {
                $this->error('Could not remove the AI bot webhook (code ' . $response->response_code . '), run the command again.');

                return Command::FAILURE;
            }
        }

        foreach (self::LEGACY_KEYS as $key) {
            $settings->forget($key);
        }

        $this->info("AI bot retired. Drafts moved to the admin panel: {$released}.");

        return Command::SUCCESS;
    }

    /**
     * Read the old bot token; an absent or undecryptable value skips the Telegram steps.
     *
     * @return string|null
     */
    private function legacyToken(): ?string
    {
        $encrypted = Setting::where('key', 'telegram_ai.token')->value('value');

        if (empty($encrypted)) {
            return null;
        }

        try {
            return (string) Crypt::decrypt($encrypted);
        } catch (\Throwable) {
            $this->warn('The stored AI bot token cannot be decrypted, skipping Telegram cleanup.');

            return null;
        }
    }

    /**
     * Delete the draft, or strip its buttons when Telegram refuses to delete an old message.
     *
     * @param AiMessage $draft
     * @param string    $groupId
     * @param string    $token
     *
     * @return string done|revoked|unavailable
     */
    private function removeDraftFromTopic(AiMessage $draft, string $groupId, string $token): string
    {
        $params = ['chat_id' => $groupId, 'message_id' => $draft->message_id];

        $response = TelegramMethods::sendQueryTelegram('deleteMessage', $params, $token);
        $result = $this->classify($response);

        if ($response->ok === true || $result !== 'done') {
            return $result;
        }

        $response = TelegramMethods::sendQueryTelegram(
            'editMessageReplyMarkup',
            $params + ['reply_markup' => ['inline_keyboard' => []]],
            $token,
        );

        return $this->classify($response);
    }

    /**
     * Map a Telegram answer to the next step. Other 4xx answers mean this
     * message cannot be changed (for example, already removed) and are final.
     *
     * @param TelegramAnswerDto $response
     *
     * @return string done|revoked|unavailable
     */
    private function classify(TelegramAnswerDto $response): string
    {
        if ($response->ok === true) {
            return 'done';
        }

        return match (true) {
            $response->response_code === 401 => 'revoked',
            $response->response_code === 429, (int) $response->response_code >= 500 => 'unavailable',
            default => 'done',
        };
    }
}
