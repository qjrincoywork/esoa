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

    /**
     * Derive the type of an account from its HMS code.
     *
     * HMS stores no type column: a code beginning "TP" is a TPA account and every
     * other code an HMO one. Records stamp the derived value onto themselves and
     * listings filter on the same rule, so it is written here once — the two would
     * otherwise be free to disagree about what a given code is.
     *
     * {@see self::TPA_HMO} is never derived: it says an account is both, which is a
     * choice someone makes, not something a code can tell us.
     *
     * @param  string|null  $accountCode
     */
    public static function fromAccountCode(?string $accountCode): string
    {
        return str_starts_with(trim((string) $accountCode), 'TP')
            ? self::TPA
            : self::HMO;
    }
}
