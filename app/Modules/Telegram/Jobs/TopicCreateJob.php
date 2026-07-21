<?php

namespace App\Modules\Telegram\Jobs;

use App\Jobs\EnrichBotUserProfileJob;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Modules\Telegram\Actions\GetChat;
use App\Modules\Telegram\Actions\SendContactMessage;
use App\Modules\Telegram\Api\TelegramMethods;
use App\Services\Settings\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

class TopicCreateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    private BotUser $botUser;

    private TelegramMethods $telegramMethods;

    private int $botUserId;

    public function __construct(
        int $botUserId,
        TelegramMethods $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->botUser = BotUser::find($this->botUserId);

            $topicName = $this->generateNameTopic($this->botUser);

            $response = $this->telegramMethods->sendQueryTelegram('createForumTopic', [
                'chat_id' => (string) app(SettingsService::class)->get('telegram.group_id'),
                'name' => $topicName,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]);

            if ($response->ok === true) {
                $this->botUser->topic_id = $response->message_thread_id;
                $this->botUser->save();

                app(SendContactMessage::class)->execute($this->botUser);
                return;
            }

            if ($response->response_code === 429) {
                $retryAfter = $response->parameters->retry_after ?? 3;
                Log::warning("429 Too Many Requests. Retry after {$retryAfter} sec.");
                $this->release($retryAfter);
                return;
            }

            Log::error('TopicCreateJob: unknown error', [
                'response' => (array)$response,
            ]);
        } catch (\Throwable $e) {
            Log::channel('app')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Generate chat name.
     *
     * @param BotUser $botUser
     *
     * @return string
     */
    protected function generateNameTopic(BotUser $botUser): string
    {
        try {
            if ($botUser->platform === 'external_source') {
                $source = ExternalUser::getSourceById($botUser->chat_id);
                return "#{$botUser->chat_id} ({$source})";
            }

            $templateTopicName = (string) app(SettingsService::class)->get('telegram.template_topic_name');
            if (empty($templateTopicName)) {
                throw new \Exception('Template not found');
            }

            if (preg_match('/(\{platform})/', $templateTopicName)) {
                $templateTopicName = str_replace('{platform}', $botUser->platform, $templateTopicName);
            }

            $nameParts = $this->getPartsGenerateName($botUser);
            if (empty($nameParts)) {
                throw new \Exception('Name parts not found');
            }

            // parsing template
            preg_match_all('/{([^}]+)}/', $templateTopicName, $matches);
            if (empty($matches[1])) {
                throw new \Exception('Params template topic name not found');
            }

            $topicName = $templateTopicName;
            foreach (array_unique($matches[1]) as $param) {
                // Missing params collapse to an empty string instead of aborting
                // the whole name: a template like «{first_name} {last_name}» must
                // still work for a user who has no surname (or a VK/MAX profile
                // that only carries a single display name).
                $value = (string) ($nameParts[$param] ?? '');
                $topicName = str_replace('{' . $param . '}', $value, $topicName);
            }

            // Tidy up the whitespace left where empty params used to be.
            $topicName = trim((string) preg_replace('/\s{2,}/', ' ', $topicName));
            if ($topicName === '') {
                throw new \Exception('Empty topic name');
            }

            return $topicName;
        } catch (\Throwable $e) {
            return '#' . $botUser->chat_id . ' (' . $botUser->platform . ')';
        }
    }

    /**
     * Get parts for chat name generation.
     *
     * Telegram is resolved live from getChat. Every other platform (vk, max,
     * avito, …) is labelled from the BotUser profile stored on the row — getChat
     * only understands Telegram ids, so calling it for a VK/MAX id fails and the
     * topic used to fall back to the bare «#id (platform)» (issue #205). VK is
     * enriched here on demand (users.get) if it has not been synced yet; MAX is
     * already captured from its webhook by MaxMessageService.
     *
     * The stored single display name is exposed under every name-ish template
     * param so a Telegram-style template still resolves to something readable.
     *
     * @param BotUser $botUser
     *
     * @return array<string, string>
     */
    protected function getPartsGenerateName(BotUser $botUser): array
    {
        if ($botUser->platform === 'telegram') {
            return $this->telegramNameParts((int) $botUser->chat_id);
        }

        // Pull a profile for platforms that fetch it asynchronously (VK) so the
        // name is available at topic-creation time rather than on the next sync.
        if (empty($botUser->display_name)) {
            try {
                (new EnrichBotUserProfileJob($botUser))->handle();
                $botUser->refresh();
            } catch (\Throwable) {
                // Best effort — fall through to whatever is already stored.
            }
        }

        $displayName = (string) ($botUser->display_name ?? '');
        $username = (string) ($botUser->username ?? '');

        // No name and no username: there is nothing readable to label the topic
        // with, so signal the caller to use the «#id (platform)» fallback rather
        // than render a template down to just the bare platform literal.
        if ($displayName === '' && $username === '') {
            return [];
        }

        $parts = [
            'id' => (string) $botUser->chat_id,
            'username' => $username,
            'name' => $displayName,
            'display_name' => $displayName,
            'first_name' => $displayName,
            'last_name' => '',
        ];

        return array_filter($parts, static fn (string $value): bool => $value !== '');
    }

    /**
     * Name parts resolved live from the Telegram getChat API.
     *
     * @param int $chatId
     *
     * @return array<string, string>
     */
    protected function telegramNameParts(int $chatId): array
    {
        try {
            $chatDataQuery = app(GetChat::class)->execute($chatId);
            if (!$chatDataQuery->ok) {
                throw new \Exception('ChatData not found');
            }

            $chatData = $chatDataQuery->rawData['result'];
            if (empty($chatData)) {
                throw new \Exception('ChatData not found');
            }

            $neededKeys = [
                'id',
                'email',
                'first_name',
                'last_name',
                'username',
            ];
            $parts = array_intersect_key($chatData, array_flip($neededKeys));

            // Expose a combined «name»/«display_name» too, so a template written
            // with those params works the same across every platform.
            $combined = trim(($chatData['first_name'] ?? '') . ' ' . ($chatData['last_name'] ?? ''));
            if ($combined !== '') {
                $parts['name'] = $combined;
                $parts['display_name'] = $combined;
            }

            return array_map(static fn ($value): string => (string) $value, $parts);
        } catch (Exception $e) {
            return [];
        }
    }
}
