<?php

namespace App\DTOs\VK;

readonly class VkAnswerDto
{
    public function __construct(
        public ?array $error,
        public int|array $response,
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
            error: $dataAnswer['error'] ?? null,
            response: $dataAnswer['response'],
        );
    }
}
