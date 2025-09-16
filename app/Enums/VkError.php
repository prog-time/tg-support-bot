<?php

namespace App\Enums;

/**
 * Enum для ошибок VK Bot API
 * Поддерживает PHP 8.1+ с backed enum
 */
enum VkError: string
{
    case SYSTEM_ERROR = 'System error';
    case METHOD_PASSED = 'Unknown method passed';

    /**
     * Получить описание ошибки на русском языке
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SYSTEM_ERROR => 'Ошибка работы бота',
            self::METHOD_PASSED => 'Сообщение не изменено',
        };
    }

    /**
     * Попытаться определить ошибку по тексту ответа
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
