<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Типы кнопок для сообщений
 */
enum ButtonType: string
{
    case CALLBACK = 'callback';
    case URL = 'url';
    case PHONE = 'phone';
    case TEXT = 'text';

    /**
     * Проверяет, является ли тип inline кнопкой.
     *
     * @return bool
     */
    public function isInline(): bool
    {
        return match ($this) {
            self::CALLBACK, self::URL => true,
            self::PHONE, self::TEXT => false,
        };
    }

    /**
     * Проверяет, является ли тип reply keyboard кнопкой.
     *
     * @return bool
     */
    public function isReplyKeyboard(): bool
    {
        return !$this->isInline();
    }

    /**
     * Парсит тип кнопки из строки команды.
     *
     * @param string|null $command Команда в формате "type:value" или null
     *
     * @return self
     */
    public static function fromCommand(?string $command): self
    {
        if ($command === null || $command === '') {
            return self::TEXT;
        }

        $parts = explode(':', $command, 2);
        $type = strtolower($parts[0]);

        return match ($type) {
            'callback' => self::CALLBACK,
            'url' => self::URL,
            'phone' => self::PHONE,
            default => self::TEXT,
        };
    }
}
