<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
