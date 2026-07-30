<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class UserType extends Enum
{
    public const VC_EMPLOYEE = 1;
    public const ACCOUNT_BRANCH_ADMIN = 2;
    public const BROKER = 3;
    public const GROUP_ACCOUNT_ADMIN = 4;

    /**
     * Map a user type value to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::VC_EMPLOYEE           => 'VC Employee',
            self::ACCOUNT_BRANCH_ADMIN  => 'Account / Branch Admin',
            self::BROKER                => 'Broker',
            self::GROUP_ACCOUNT_ADMIN   => 'Group Account Admin',
            default                     => 'Unknown',
        };
    }

    /**
     * Return all user types as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::VC_EMPLOYEE,          'name' => self::label(self::VC_EMPLOYEE)],
            ['value' => self::ACCOUNT_BRANCH_ADMIN,  'name' => self::label(self::ACCOUNT_BRANCH_ADMIN)],
            ['value' => self::BROKER,                'name' => self::label(self::BROKER)],
            ['value' => self::GROUP_ACCOUNT_ADMIN,   'name' => self::label(self::GROUP_ACCOUNT_ADMIN)],
        ];
    }
}
