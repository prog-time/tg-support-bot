<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

/**
 * DTO for API message response.
 *
 * @property bool $status
 * @property int|null $message_id
 * @property string|null $error
 */
class ExternalMessageAnswerDto extends Data
{
    /**
     * @param bool $status
     * @param string|null $error
     * @param ExternalMessageResponseDto|null $result
     */
    public function __construct(
        public bool $status,
        public ?string $error,
        public ?ExternalMessageResponseDto $result,
    ) {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return array_filter(parent::toArray(), fn($value) => !is_null($value));
    }

}
