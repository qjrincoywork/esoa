<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class IsActive extends Enum
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    public static function label($value): string
    {
        return match ($value) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::ACTIVE, 'name' => self::label(self::ACTIVE)],
            ['value' => self::INACTIVE, 'name' => self::label(self::INACTIVE)],
        ];
    }
}
