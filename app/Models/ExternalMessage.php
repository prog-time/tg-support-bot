<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int            $id
 * @property int            $external_id
 * @property string         $source
 * @property string         $file_id
 * @property string         $file_url
 * @property string         $updated_at
 * @property string         $created_at
 * @property ExternalSource $externalSource
 */
class ExternalMessage extends Model
{
    protected $table = 'external_messages';

    protected $fillable = [
        'message_id',
        'text',
        'file_id',
    ];

    /**
     * @return HasOne
     */
    public function externalSource(): HasOne
    {
        return $this->hasOne(ExternalSource::class);
    }
}
