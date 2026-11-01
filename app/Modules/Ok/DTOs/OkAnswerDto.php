<?php

namespace App\Modules\Ok\DTOs;

readonly class OkAnswerDto
{
    /**
     * @param int         $response_code 200 on success, 500 on any failure
     * @param string|null $error_message Error without credentials
     * @param mixed       $response      Decoded API answer
     */
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
        return new self(
            response_code: $dataAnswer['response_code'] ?? 200,
            error_message: $dataAnswer['error_message'] ?? null,
            response: $dataAnswer['response'] ?? null,
        );
    }
}
