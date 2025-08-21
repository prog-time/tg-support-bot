<?php

namespace App\DTOs\External;

use Spatie\LaravelData\Data;

/**
 * DTO для результата списка сообщений
 *
 * @param string $source
 * @param string $external_id
 * @param array $messages
 */
class ExternalListMessageAnswerDto extends Data
{
    /**
     * @param string $source
     * @param string $external_id
     * @param array $messages
     */
    public function __construct(
        public bool $status,
        public string $source,
        public string $external_id,
        public array $messages,
    ) {
    }
}
