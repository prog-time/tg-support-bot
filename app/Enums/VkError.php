<?php

namespace App\Enums;

/**
 * Enum for VK Bot API errors.
 */
enum VkError: string
{
    case SYSTEM_ERROR = 'System error';
    case METHOD_PASSED = 'Unknown method passed';

    /**
     * Get error description.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SYSTEM_ERROR => 'Bot error',
            self::METHOD_PASSED => 'Message not modified',
        };
    }

    /**
     * Try to determine error by response text.
     */
    public static function fromResponse(string $description): ?self
    {
        foreach (self::cases() as $error) {
            if ($description === $error->value) {
                return $error;
            }
        }

        foreach (self::cases() as $error) {
            if (str_contains($description, $error->value)) {
                return $error;
            }
        }

        return null;
    }
}
