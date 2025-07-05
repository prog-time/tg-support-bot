<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property string $name
 * @property string $webhook_url
 * @property int    $user_id
 * @property-read User $user
 */
class ExternalSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'webhook_url',
        'user_id',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
