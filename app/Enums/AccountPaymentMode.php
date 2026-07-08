<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class AccountPaymentMode extends Enum
{
    public const BANK_DEPOSIT = 1;
    public const ONLINE_TRANSFER = 2;
    public const CHECK = 3;
    public const CASH = 4;
    public const OTHERS = 5;

    /**
     * Map a payment mode value to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::BANK_DEPOSIT => 'Bank Deposit',
            self::ONLINE_TRANSFER => 'Online Transfer',
            self::CHECK => 'Check',
            self::CASH => 'Cash',
            self::OTHERS => 'Others',
            default => 'Unknown',
        };
    }

    /**
     * Return all payment modes as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::BANK_DEPOSIT, 'name' => self::label(self::BANK_DEPOSIT)],
            ['value' => self::ONLINE_TRANSFER, 'name' => self::label(self::ONLINE_TRANSFER)],
            ['value' => self::CHECK, 'name' => self::label(self::CHECK)],
            ['value' => self::CASH, 'name' => self::label(self::CASH)],
            ['value' => self::OTHERS, 'name' => self::label(self::OTHERS)],
        ];
    }
}
