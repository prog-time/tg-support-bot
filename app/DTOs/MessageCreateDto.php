<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO for message creation.
 */
readonly class MessageCreateDto
{
    public function __construct(
        public string $text,
        public int $chatId,
    ) {
    }
}
