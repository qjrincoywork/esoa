<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class SoaStatus extends Enum
{
    public const UNPAID = 1;
    public const ENDORSED = 2;
    public const PAID = 3;
    public const DISPUTED = 4;

    public static function label($value): string
    {
        return match ($value) {
            self::UNPAID => 'Unpaid',
            self::ENDORSED => 'Endorsed',
            self::PAID => 'Paid',
            self::DISPUTED => 'Disputed',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::UNPAID => 'p-3 rounded-lg bg-red-500/20 text-red-500 border-red-500/30',
            self::ENDORSED => 'p-3 rounded-lg bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::PAID => 'p-3 rounded-lg bg-green-500/20 text-green-500 border-green-500/30',
            self::DISPUTED => 'p-3 rounded-lg bg-blue-500/20 text-blue-500 border-blue-500/30',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::UNPAID, 'name' => self::label(self::UNPAID)],
            ['value' => self::ENDORSED, 'name' => self::label(self::ENDORSED)],
            ['value' => self::PAID, 'name' => self::label(self::PAID)],
            ['value' => self::DISPUTED, 'name' => self::label(self::DISPUTED)],
        ];
    }
}
