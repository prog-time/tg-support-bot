<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO для создания сообщения
 */
readonly class MessageCreateDto
{
    public function __construct(
        public string $text,
        public int $chatId,
    ) {
    }
}
