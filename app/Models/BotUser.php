<?php

namespace App\Models;

use App\Actions\Telegram\SendContactMessage;
use App\DTOs\TelegramUpdateDto;
use App\Logging\LokiLogger;
use App\Services\TgTopicService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int    $topic_id
 * @property int    $chat_id
 * @property string $platform
 * @property-read ExternalUser $externalUser
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
     * Create new TG topic
     *
     * @return int|null
     */
    public function saveNewTopic(): ?int
    {
        try {
            $tgTopicService = new TgTopicService();
            $dataTopic = $tgTopicService->createNewTgTopic($this);

            $this->topic_id = $dataTopic->message_thread_id;
            $this->save();

            (new SendContactMessage())->executeByBotUser($this);

            return $dataTopic->message_thread_id;
        } catch (\Exception $e) {
            return null;
        }
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
            if ($update->typeSource === 'supergroup') {
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
                if (empty($botUser->topic_id)) {
                    $botUser->saveNewTopic();
                }
            }

            return $botUser ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getUserByChatId(string|int $chatId, string $platform): ?BotUser
    {
        try {
            $botUser = self::firstOrCreate(
                [
                    'chat_id' => $chatId,
                ],
                [
                    'platform' => $platform,
                ]
            );
            if (empty($botUser->topic_id)) {
                $botUser->saveNewTopic();
            }

            return $botUser;
        } catch (\Exception $e) {
            return null;
        }
    }
}
