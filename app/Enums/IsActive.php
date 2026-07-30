<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class IsActive extends Enum
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    /**
     * Map an active-flag value to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    /**
     * Return both active/inactive states as {value, name} arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::ACTIVE, 'name' => self::label(self::ACTIVE)],
            ['value' => self::INACTIVE, 'name' => self::label(self::INACTIVE)],
        ];
    }
}
