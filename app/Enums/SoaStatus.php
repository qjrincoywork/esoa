<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class SoaStatus extends Enum
{
    public const UNPAID = 1;
    public const ENDORSED = 2;
    public const PAID = 3;

    public static function label($value): string
    {
        return match ($value) {
            self::UNPAID => 'Unpaid',
            self::ENDORSED => 'Endorsed',
            self::PAID => 'Paid',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::UNPAID => 'bg-red-500/20 text-red-500 border-red-500/30',
            self::ENDORSED => 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::PAID => 'bg-green-500/20 text-green-500 border-green-500/30',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::UNPAID, 'name' => self::label(self::UNPAID)],
            ['value' => self::ENDORSED, 'name' => self::label(self::ENDORSED)],
            ['value' => self::PAID, 'name' => self::label(self::PAID)],
        ];
    }
}
