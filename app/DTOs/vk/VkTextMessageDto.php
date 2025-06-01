<?php

namespace App\DTOs\VK;

use Spatie\LaravelData\Data;

/**
 * Отправка сообщения в VK
 *
 * @property string $methodQuery
 * @property string|null $title
 * @property string|null $file
 * @property int|null $peer_id
 * @property string|null $message
 * @property string|null $attachment
 */
class VkTextMessageDto extends Data
{
    /**
     * @param string $methodQuery
     * @param string|null $title
     * @param string|null $file
     * @param int|null $peer_id
     * @param string|null $message
     * @param string|null $attachment
     */
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
