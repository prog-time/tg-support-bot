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
 * @property string         $file_type
 * @property string         $file_name
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
        'file_type',
        'file_name',
    ];

    /**
     * @return HasOne
     */
    public function externalSource(): HasOne
    {
        return $this->hasOne(ExternalSource::class);
    }
}
