<?php

namespace App\DTOs\VK;

use Spatie\LaravelData\Data;

class VkTextMessageDto extends Data
{
    public function __construct(
        public string $methodQuery,
        public ?string $title,
        public ?string $file,
        public ?int $peer_id,
        public ?string $message,
        public ?string $attachment
    ) {}

    /**
     * @return array
     */
    public function toArray(): array
    {
        $dataMessage = array_filter(parent::toArray(), fn($value) => !is_null($value));
        unset($dataMessage['methodQuery']);
        return $dataMessage;
    }
}
