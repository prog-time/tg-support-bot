<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $message_id
 * @property string $file_id
 * @property string $file_type
 * @property string|null $file_name
 * @property-read Message $message
 */
class MessageAttachment extends Model
{
    protected $table = 'message_attachments';

    protected $fillable = [
        'message_id',
        'file_id',
        'file_type',
        'file_name',
    ];

    /**
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
