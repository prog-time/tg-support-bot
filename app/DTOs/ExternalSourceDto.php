<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class ExternalSourceDto extends Data
{
    /**
     * @param int|null    $id
     * @param string      $name
     * @param string|null $webhook_url
     * @param string|null $created_at
     * @param string|null $updated_at
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $webhook_url,
        public ?string $created_at,
        public ?string $updated_at,
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn ($value) => !is_null($value));
    }
}
