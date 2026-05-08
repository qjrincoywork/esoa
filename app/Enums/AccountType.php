<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class AccountType extends Enum
{
    public const TPA_HMO = 'T';
    public const HMO = 'H';

    public static function label($value): string
    {
        return match ($value) {
            self::TPA_HMO => 'TPA/HMO',
            self::HMO => 'HMO',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::TPA_HMO, 'name' => self::label(self::TPA_HMO)],
            ['value' => self::HMO, 'name' => self::label(self::HMO)],
        ];
    }
}
