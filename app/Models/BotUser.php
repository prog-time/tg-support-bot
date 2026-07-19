<?php

namespace App\Models;

use App\Jobs\EnrichBotUserProfileJob;
use App\Modules\External\DTOs\ExternalMessageDto;
use App\Modules\Telegram\DTOs\TelegramUpdateDto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use phpDocumentor\Reflection\Exception;

/**
 * @property int                             $id
 * @property int                             $topic_id
 * @property int|string                      $chat_id
 * @property int|null                        $external_user_id
 * @property string                          $platform
 * @property string|null                     $display_name
 * @property string|null                     $username
 * @property string|null                     $avatar_path
 * @property \Illuminate\Support\Carbon|null $profile_synced_at
 * @property mixed                           $aiCondition
 * @property mixed                           $lastMessageManager
 * @property ExternalUser|null               $externalUser
 * @property bool                            $is_banned
 * @property bool                            $is_closed
 * @property string|null                     $closed_at
 * @property \Illuminate\Support\Carbon|null $manager_last_read_at
 */
class BotUser extends Model
{
    use HasFactory;

    protected $table = 'bot_users';

    protected $fillable = [
        'chat_id',
        'external_user_id',
        'topic_id',
        'platform',
        'display_name',
        'username',
        'avatar_path',
        'profile_synced_at',
        'is_banned',
        'banned_at',
        'is_closed',
        'closed_at',
        'manager_last_read_at',
    ];

    protected $casts = [
        'manager_last_read_at' => 'datetime',
        'profile_synced_at' => 'datetime',
    ];

    /**
     * The External Sources user behind this conversation, if any.
     *
     * Keyed on the dedicated `external_user_id` column rather than `chat_id`.
     * `chat_id` used to double as this foreign key, which broke on PostgreSQL
     * once the column became a string: a non-numeric id (Avito "u2i-…") is
     * rejected as a bigint (22P02), and whereHas() compared varchar to bigint
     * (42883) even with no such rows present.
     *
     * @return HasOne
     */
    public function externalUser(): HasOne
    {
        return $this->hasOne(ExternalUser::class, 'id', 'external_user_id');
    }

    /**
     * @return HasOne
     */
    public function aiCondition(): HasOne
    {
        return $this->hasOne(AiCondition::class);
    }

    /**
     * @return HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'id', 'bot_user_id');
    }

    /**
     * Newest message of the conversation, by message date.
     *
     * Resolved by greatest `created_at` (tie-broken by `id`) rather than the
     * default `id`-only, so the dialog-list preview/timestamp and the
     * "last activity" sort agree even when messages are persisted out of id
     * order by queued jobs.
     *
     * @return HasOne
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany(['created_at', 'id']);
    }

    /**
     * @return HasOne
     */
    public function lastMessageManager(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(['created_at' => 'max'], function ($q) {
            $q->where('message_type', 'outgoing');
        });
    }

    /**
     * Get platform by chat id
     *
     * @param int|string $chatId
     *
     * @return string|null
     */
    public static function getPlatformByChatId(int|string $chatId): ?string
    {
        try {
            $botUser = self::select('platform')
                ->where('chat_id', $chatId)
                ->first();

            return $botUser ? $botUser->platform : null;
        } catch (\Throwable $e) {
            Log::channel('app')->error('File: ' . $e->getFile() . '; Line: ' . $e->getLine() . '; Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get platform by topic id
     *
     * @param int $messageThreadId
     *
     * @return string|null
     */
    public static function getPlatformByTopicId(int $messageThreadId): ?string
    {
        try {
            $botUser = self::select('platform')
                ->where('topic_id', $messageThreadId)
                ->first();

            return $botUser->platform ?? null;
        } catch (\Throwable $e) {
            Log::channel('app')->error('File: ' . $e->getFile() . '; Line: ' . $e->getLine() . '; Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Geg user data
     *
     * @param TelegramUpdateDto $update
     *
     * @return BotUser|null
     */
    public static function getOrCreateByTelegramUpdate(TelegramUpdateDto $update): ?BotUser
    {
        try {
            if ($update->typeSource === 'supergroup' && !empty($update->messageThreadId)) {
                $botUser = self::where('topic_id', $update->messageThreadId)
                    ->with('externalUser')
                    ->first();
            } elseif ($update->typeSource === 'private') {
                $botUser = self::firstOrCreate(
                    [
                        'chat_id' => $update->chatId,
                    ],
                    [
                        'platform' => 'telegram',
                    ]
                );

                if ($botUser->wasRecentlyCreated) {
                    // New user — fill profile from DTO synchronously.
                    $fill = [];
                    if ($update->displayName !== null) {
                        $fill['display_name'] = $update->displayName;
                    }
                    if ($update->username !== null) {
                        $fill['username'] = $update->username;
                    }
                    if (!empty($fill)) {
                        $botUser->update($fill);
                    }
                } else {
                    // Existing user — opportunistically update only if changed.
                    $fill = [];
                    if ($update->displayName !== null && $botUser->display_name !== $update->displayName) {
                        $fill['display_name'] = $update->displayName;
                    }
                    if ($update->username !== null && $botUser->username !== $update->username) {
                        $fill['username'] = $update->username;
                    }
                    if (!empty($fill)) {
                        $botUser->update($fill);
                    }
                }

                self::maybeEnrichProfile($botUser);
            }

            return $botUser ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param int|null $messageThreadId
     *
     * @return BotUser|null
     */
    public static function getByTopicId(?int $messageThreadId): ?BotUser
    {
        try {
            if ($messageThreadId) {
                return self::where('topic_id', $messageThreadId)
                    ->with('externalUser')
                    ->orderByDesc('id')
                    ->first();
            } else {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param string|int $chatId
     * @param string     $platform
     *
     * @return BotUser|null
     */
    public static function getUserByChatId(string|int $chatId, string $platform): ?BotUser
    {
        try {
            $botUser = self::firstOrCreate([
                'chat_id' => $chatId,
            ], [
                'platform' => $platform,
            ]);

            self::maybeEnrichProfile($botUser);

            return $botUser;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Dispatch the async profile-enrichment job only when a (re)sync is due —
     * i.e. the profile has never been synced or its data is older than the TTL.
     *
     * Guarding the dispatch (not just the job body) avoids enqueuing a no-op job
     * on every incoming message; the job's own TTL check is the final safety net.
     *
     * @param BotUser $botUser
     *
     * @return void
     */
    private static function maybeEnrichProfile(BotUser $botUser): void
    {
        $syncedAt = $botUser->profile_synced_at;

        if ($syncedAt === null || $syncedAt->diffInDays(now()) >= EnrichBotUserProfileJob::SYNC_TTL_DAYS) {
            dispatch(new EnrichBotUserProfileJob($botUser));
        }
    }

    /**
     * @param ExternalMessageDto $updateData
     *
     * @return BotUser|null
     */
    public function getOrCreateExternalBotUser(ExternalMessageDto $updateData): ?BotUser
    {
        try {
            $this->externalUser = ExternalUser::firstOrCreate([
                'external_id' => $updateData->external_id,
                'source' => $updateData->source,
            ]);

            if (empty($this->externalUser)) {
                throw new Exception('External user not found!');
            }

            return BotUser::firstOrCreate(
                [
                    'external_user_id' => $this->externalUser->id,
                    'platform' => $this->externalUser->source,
                ],
                [
                    // chat_id is kept in sync for backward compatibility: it used
                    // to be this foreign key, and older reads still expect it.
                    'chat_id' => (string) $this->externalUser->id,

                    // External users are anonymous — give the admin card a readable
                    // label instead of the raw internal chat_id (e.g. «-1»).
                    'display_name' => 'Посетитель сайта',
                ],
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param string $external_id
     * @param string $source
     *
     * @return BotUser|null
     */
    public function getExternalBotUser(string $external_id, string $source): ?BotUser
    {
        try {
            $this->externalUser = ExternalUser::where([
                'external_id' => $external_id,
                'source' => $source,
            ])->first();

            if (empty($this->externalUser)) {
                throw new Exception('External user not found!');
            }

            return BotUser::where([
                'external_user_id' => $this->externalUser->id,
                'platform' => $this->externalUser->source,
            ])->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return HasMany
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * @return bool
     */
    public function isBanned(): bool
    {
        return $this->is_banned ?? false;
    }

    /**
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->is_closed ?? false;
    }
}
