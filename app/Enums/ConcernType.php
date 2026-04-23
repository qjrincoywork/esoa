<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class ConcernType extends Enum
{
    public const ICT = 1;
    public const MDA = 2;
    public const BILLING = 3;
    public const OTHERS = 4;

    public static function label($value): string
    {
        return match ($value) {
            self::ICT => 'Scanned Documents',
            self::MDA => 'Member Concerns',
            self::BILLING => 'Billing Concerns',
            self::OTHERS => 'Other Concerns',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::ICT => 'bg-blue-500/20 text-blue-500 border-blue-500/30',
            self::MDA => 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::BILLING => 'bg-orange-500/20 text-orange-500 border-orange-500/30',
            self::OTHERS => 'bg-green-500/20 text-green-500 border-green-500/30',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::ICT, 'name' => self::label(self::ICT)],
            ['value' => self::MDA, 'name' => self::label(self::MDA)],
            ['value' => self::BILLING, 'name' => self::label(self::BILLING)],
            ['value' => self::OTHERS, 'name' => self::label(self::OTHERS)],
        ];
    }
}
