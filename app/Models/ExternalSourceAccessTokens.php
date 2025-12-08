<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 */
class ExternalSourceAccessTokens extends Model
{
    use HasFactory;

    protected $table = 'external_source_access_tokens';

    protected $fillable = [
        'external_source_id',
        'token',
        'token',
        'active',
    ];

    /**
     * @return BelongsTo
     */
    public function external_source(): BelongsTo
    {
        return $this->belongsTo(ExternalSource::class);
    }
}
