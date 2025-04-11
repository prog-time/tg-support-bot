<?php

namespace App\Models;

use App\Actions\Telegram\SendContactMessage;
use App\DTOs\TelegramUpdateDto;
use App\Services\TgTopicService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $topic_id
 * @property int $chat_id
 */
class BotUser extends Model
{
    use HasFactory;

    protected $table = 'bot_users';

    protected $fillable = [
        'chat_id',
        'topic_id',
        'platform'
    ];

    /**
     * Create new TG topic
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
     * Geg user data
     * @param TelegramUpdateDto $update
     * @param string $source
     * @return BotUser|null
     */
    public static function getUserData(TelegramUpdateDto $update, string $source): ?BotUser
    {
        try {
            if ($update->typeSource === 'supergroup') {
                $botUser = self::where('topic_id', $update->messageThreadId)->first();
            } else if ($update->typeSource === 'private') {
                $botUser = self::firstOrCreate(
                    [
                        'chat_id' => $update->chatId
                    ],
                    [
                        'platform' => $source
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
}
