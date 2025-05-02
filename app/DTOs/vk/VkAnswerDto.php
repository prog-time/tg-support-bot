<?php

namespace App\DTOs\Vk;

readonly class VkAnswerDto
{
    public function __construct(
        public int $response,
    ) {}

    public static function fromData(array $dataAnswer): self
    {
        return new self(
            response: $dataAnswer['response']
        );
    }
}
