<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int    $id
 * @property int    $external_id
 * @property string $source
 * @property string $updated_at
 * @property string $created_at
 * @property-read ExternalSource $externalSource
 * @property-read BotUser $botUser
 */
class ExternalUser extends Model
{
    use HasFactory;

    protected $table = 'external_users';

    protected $fillable = [
        'id',
        'external_id',
        'source',
        'updated_at',
        'created_at',
    ];

    /**
     * @return BelongsTo
     */
    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class, 'chat_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function externalSource(): HasOne
    {
        return $this->hasOne(ExternalSource::class, 'name', 'source');
    }

    /**
     * @param int $id
     *
     * @return string
     */
    public static function getSourceById(int $id): string
    {
        $externalUser = self::select('source')->where('id', $id)->first();
        return $externalUser->source ?? '';
    }
}
