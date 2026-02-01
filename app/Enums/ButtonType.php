<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Button types for messages.
 */
enum ButtonType: string
{
    case CALLBACK = 'callback';
    case URL = 'url';
    case PHONE = 'phone';
    case TEXT = 'text';

    /**
     * Check if type is inline button.
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
     * Check if type is reply keyboard button.
     *
     * @return bool
     */
    public function isReplyKeyboard(): bool
    {
        return !$this->isInline();
    }

    /**
     * Parse button type from command string.
     *
     * @param string|null $command Command in "type:value" format or null
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
