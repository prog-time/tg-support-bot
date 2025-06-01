<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalUser extends Model
{
    protected $table = 'external_users';

    protected $fillable = [
        'id',
        'external_id',
        'source',
        'updated_at',
        'created_at',
    ];

    public static function getSourceById(int $id): string
    {
        $externalUser = self::select('source')->where('id', $id)->first();
        return $externalUser->source ?? '';
    }
}
