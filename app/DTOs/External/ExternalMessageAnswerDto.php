<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

/**
 * DTO для ответа на работу с сообщениями через API
 *
 * @property bool $status
 * @property int|null $message_id
 * @property string|null $error
 */
class ExternalMessageAnswerDto extends Data
{
    /**
     * @param bool $status
     * @param int|null $message_id
     * @param string|null $error
     */
    public function __construct(
        public bool $status,
        public ?int $message_id,
        public ?string $error,
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
