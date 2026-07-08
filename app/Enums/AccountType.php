<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class AccountType extends Enum
{
    public const TPA_HMO = 'A';
    public const TPA = 'T';
    public const HMO = 'H';

    /**
     * Map an account type code to its human-readable label.
     *
     * @param string $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::TPA_HMO => 'TPA/HMO',
            self::TPA => 'TPA',
            self::HMO => 'HMO',
        };
    }

    /**
     * Return all account types as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:string,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::TPA_HMO, 'name' => self::label(self::TPA_HMO)],
            ['value' => self::TPA, 'name' => self::label(self::TPA)],
            ['value' => self::HMO, 'name' => self::label(self::HMO)],
        ];
    }
}
