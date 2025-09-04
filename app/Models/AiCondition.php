<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiCondition extends Model
{
    use HasFactory;

    protected $table = 'ai_conditions';

    protected $fillable = [
        'bot_user_id',
        'active',
    ];
}
