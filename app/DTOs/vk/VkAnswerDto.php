<?php

namespace App\DTOs\VK;

/**
 * Ответ от VK
 *
 * @property array|null $error
 * @property array|int $response
 */
readonly class VkAnswerDto
{
    /**
     * @param array|null $error
     * @param array|int $response
     */
    public function __construct(
        public ?array $error,
        public int|array $response,
    ) {}

    /**
     * @param array $dataAnswer
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
