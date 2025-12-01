<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class Status extends Enum
{
    public const ACTIVE = 1;
    public const INACTIVE = 0;

    public static function label($value): string
    {
        return match ($value) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'In Active',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::ACTIVE => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
            self::INACTIVE => 'bg-red-500/20 text-red-300 border-red-500/30',
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
