<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI knowledge-base entry: a frequently asked question and its reference answer.
 *
 * Enabled entries are appended to the AI system prompt by
 * {@see \App\Modules\Ai\Services\AiSystemPromptLoader}.
 *
 * @property int                        $id
 * @property string                     $question
 * @property string                     $answer
 * @property bool                       $enabled
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AiFaqEntry extends Model
{
    protected $table = 'ai_faq_entries';

    /**
     * Mass-assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
