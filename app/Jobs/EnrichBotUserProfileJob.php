<?php

namespace App\Jobs;

use App\Models\BotUser;
use App\Modules\Api\Services\FileService;
use App\Modules\Telegram\Actions\GetChat;
use App\Modules\Telegram\Api\TelegramHttp;
use App\Modules\Vk\Api\VkMethods;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnrichBotUserProfileJob implements ShouldQueue
{
    use Queueable;

    public const SYNC_TTL_DAYS = 30;

    /**
     * @param BotUser $botUser
     */
    public function __construct(private readonly BotUser $botUser)
    {
    }

    /**
     * Execute the job.
     *
     * Fetches the profile avatar from Telegram or VK and stores it locally.
     * For other platforms, just records the sync timestamp.
     *
     * @return void
     */
    public function handle(): void
    {
        if (
            $this->botUser->profile_synced_at !== null
            && $this->botUser->profile_synced_at->diffInDays(now()) < self::SYNC_TTL_DAYS
        ) {
            return;
        }

        try {
            match ($this->botUser->platform) {
                'telegram' => $this->enrichFromTelegram(),
                'vk' => $this->enrichFromVk(),
                default => $this->botUser->update(['profile_synced_at' => now()]),
            };
        } catch (\Throwable $e) {
            Log::channel('app')->error(
                'EnrichBotUserProfileJob failed',
                [
                    'bot_user_id' => $this->botUser->id,
                    'platform' => $this->botUser->platform,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Fetch avatar from Telegram getChat and store it locally.
     *
     * @return void
     */
    private function enrichFromTelegram(): void
    {
        $answer = app(GetChat::class)->execute((int) $this->botUser->chat_id);

        $fileId = $answer->rawData['result']['photo']['small_file_id'] ?? null;

        if ($fileId !== null) {
            $fileData = app(FileService::class)->getTelegramFile((string) $fileId);
            $filePath = $fileData['result']['file_path'] ?? null;

            if ($filePath !== null) {
                $token = (string) app(SettingsService::class)->get('telegram.token');
                $response = TelegramHttp::client()->get("https://api.telegram.org/file/bot{$token}/{$filePath}");

                if ($response->ok()) {
                    $storagePath = "avatars/bot-user-{$this->botUser->id}.jpg";
                    Storage::disk('local')->put($storagePath, $response->body());
                    $this->botUser->update([
                        'avatar_path' => $storagePath,
                        'profile_synced_at' => now(),
                    ]);

                    return;
                }
            }
        }

        $this->botUser->update(['profile_synced_at' => now()]);
    }

    /**
     * Fetch profile data from VK users.get and store avatar locally.
     *
     * @return void
     */
    private function enrichFromVk(): void
    {
        $vkAnswer = VkMethods::sendQueryVk('users.get', [
            'user_ids' => $this->botUser->chat_id,
            'fields' => 'photo_200',
        ]);

        $user = null;
        if (is_array($vkAnswer->response) && !empty($vkAnswer->response[0])) {
            $user = $vkAnswer->response[0];
        }

        $update = ['profile_synced_at' => now()];

        if ($user !== null) {
            $firstName = $user['first_name'] ?? null;
            $lastName = $user['last_name'] ?? null;
            $name = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));

            if ($name !== '' && $this->botUser->display_name === null) {
                $update['display_name'] = $name;
            }

            $photoUrl = $user['photo_200'] ?? null;
            if ($photoUrl !== null) {
                $response = Http::get($photoUrl);
                if ($response->ok()) {
                    $storagePath = "avatars/bot-user-{$this->botUser->id}.jpg";
                    Storage::disk('local')->put($storagePath, $response->body());
                    $update['avatar_path'] = $storagePath;
                }
            }
        }

        $this->botUser->update($update);
    }
}
