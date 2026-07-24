<?php

namespace App\Modules\Avito\DTOs;

use Spatie\LaravelData\Data;

/**
 * Outgoing Avito message parameters. Mirrors the host's MaxTextMessageDto:
 * toArray() drops nulls and the internal methodQuery field before the payload
 * is handed to the API client.
 */
class AvitoTextMessageDto extends Data
{
    public function __construct(
        public string $methodQuery,
        public int|string $chat_id,
        public ?string $text = null,
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
