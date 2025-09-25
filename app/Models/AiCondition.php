<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCondition extends Model
{
    use HasFactory;

    protected $table = 'ai_conditions';

    protected $fillable = [
        'bot_user_id',
        'active',
    ];

    /**
     * @return BelongsTo
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
}
