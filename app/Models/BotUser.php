<?php

namespace App\Models;

use App\DTOs\External\ExternalMessageDto;
use App\DTOs\TelegramUpdateDto;
use App\Logging\LokiLogger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 */
class BotUser extends Model
{
    use HasFactory;

    protected $table = 'bot_users';

    protected $fillable = [
        'chat_id',
        'topic_id',
        'platform',
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
        } catch (\Exception $e) {
            (new LokiLogger())->sendBasicLog($e);
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
        } catch (\Exception $e) {
            (new LokiLogger())->sendBasicLog($e);
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
    public static function getTelegramUserData(TelegramUpdateDto $update): ?BotUser
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param ExternalMessageDto $updateData
     *
     * @return BotUser|null
     */
    public function getExternalBotUser(ExternalMessageDto $updateData): ?BotUser
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
        } catch (\Exception $e) {
            return null;
        }
    }
}
