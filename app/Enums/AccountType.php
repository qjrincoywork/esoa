<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class AccountType extends Enum
{
    // public const TPA_HMO = 'A';
    public const TPA = 'T';
    public const HMO = 'H';

    public static function label($value): string
    {
        return match ($value) {
            // self::TPA_HMO => 'TPA/HMO',
            self::TPA => 'TPA',
            self::HMO => 'HMO',
        };
    }

    public static function list(): array
    {
        return [
            // ['value' => self::TPA_HMO, 'name' => self::label(self::TPA_HMO)],
            ['value' => self::TPA, 'name' => self::label(self::TPA)],
            ['value' => self::HMO, 'name' => self::label(self::HMO)],
        ];
    }
}
