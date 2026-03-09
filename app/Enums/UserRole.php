<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles for admin panel users.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Manager => 'Менеджер',
        };
    }

    /**
     * Badge color for Filament.
     *
     * @return string
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'info',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value',
        );
    }
}
