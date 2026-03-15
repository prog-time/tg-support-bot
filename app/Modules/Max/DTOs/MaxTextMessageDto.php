<?php

namespace App\Modules\Max\DTOs;

use Spatie\LaravelData\Data;

class MaxTextMessageDto extends Data
{
    public function __construct(
        public string $methodQuery,
        public int $user_id,
        public ?string $text = null,
        public ?string $attachment_url = null,
        public ?string $file_token = null,
        public ?array $keyboard = null,
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $data = array_filter(parent::toArray(), fn ($value) => !is_null($value));
        unset($data['methodQuery']);

        return $data;
    }
}
