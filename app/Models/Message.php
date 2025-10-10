<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int             $bot_user_id
 * @property string          $platform
 * @property string          $message_type
 * @property int             $from_id
 * @property int             $to_id
 * @property ExternalMessage $externalMessage
 */
class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_user_id',
        'platform',
        'message_type',
        'from_id',
        'to_id',
    ];

    /**
     * @return HasOne
     */
    public function externalMessage(): HasOne
    {
        return $this->hasOne(ExternalMessage::class);
    }

    /**
     * @return BelongsTo
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    /**
     * @param string $typeMessage
     * @param int    $from_id
     * @param string $source
     *
     * @return Message|null
     */
    public static function getMessageData(string $typeMessage, int $from_id, string $source): ?Message
    {
        try {
            $messageData = static::where([
                'message_type' => $typeMessage,
                'from_id' => $from_id,
                'platform' => $source,
            ])->first();

            if (empty($messageData)) {
                throw new \Exception('Сообщение не найдено!');
            }

            return $messageData;
        } catch (\Throwable $th) {
            return null;
        }
    }
}
