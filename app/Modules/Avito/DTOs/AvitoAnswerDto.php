<?php

namespace App\Modules\Avito\DTOs;

/**
 * Normalized Avito API response. Mirrors the host's MaxAnswerDto.
 */
readonly class AvitoAnswerDto
{
    public function __construct(
        public int $response_code,
        public ?string $error_message,
        public mixed $response,
    ) {
    }

    /**
     * @param array $dataAnswer
     *
     * @return self
     */
    public static function fromData(array $dataAnswer): self
    {
        if (empty($dataAnswer['response_code'])) {
            $dataAnswer['response_code'] = 200;
        }

        return new self(
            response_code: $dataAnswer['response_code'],
            error_message: $dataAnswer['error_message'] ?? null,
            response: $dataAnswer['response'] ?? null,
        );
    }
}
