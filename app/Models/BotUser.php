<?php

namespace App\Models;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramUpdateDto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use phpDocumentor\Reflection\Exception;

/**
 * @property int               $id
 * @property int               $topic_id
 * @property int               $chat_id
 * @property string            $platform
 * @property mixed             $aiCondition
 * @property mixed             $lastMessageManager
 * @property ExternalUser|null $externalUser
 * @property bool              $is_banned
 * @property bool              $is_closed
 * @property string|null       $closed_at
 */
class BotUser extends Model
{
    use HasFactory;

    protected $table = 'bot_users';

    protected $fillable = [
        'chat_id',
        'topic_id',
        'platform',
        'is_banned',
        'banned_at',
        'is_closed',
        'closed_at',
    ];

    /**
     * @return HasOne
     */
    public function externalUser(): HasOne
    {
        return $this->hasOne(ExternalUser::class, 'id', 'chat_id');
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
     * @return HasOne
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
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
     * @param int $chatId
     *
     * @return string|null
     */
    public static function getPlatformByChatId(int $chatId): ?string
    {
        try {
            $botUser = self::select('platform')
                ->where('chat_id', $chatId)
                ->first();

            return $botUser ? $botUser->platform : null;
        } catch (\Throwable $e) {
            Log::channel('loki')->error('File: ' . $e->getFile() . '; Line: ' . $e->getLine() . '; Error: ' . $e->getMessage());
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
            Log::channel('loki')->error('File: ' . $e->getFile() . '; Line: ' . $e->getLine() . '; Error: ' . $e->getMessage());
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
            return self::firstOrCreate([
                'chat_id' => $chatId,
            ], [
                'platform' => $platform,
            ]);
        } catch (\Throwable $e) {
            return null;
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

            return BotUser::firstOrCreate([
                'chat_id' => $this->externalUser->id,
                'platform' => $this->externalUser->source,
            ]);
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
                'chat_id' => $this->externalUser->id,
                'platform' => $this->externalUser->source,
            ])->first();
        } catch (\Throwable $e) {
            return null;
        }
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
