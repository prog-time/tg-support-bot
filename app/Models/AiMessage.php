<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_messages';

    protected $fillable = [
        'bot_user_id',
        'message_id',
        'text_ai',
        'text_manager',
    ];

    /**
     * @return BelongsTo
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
}
