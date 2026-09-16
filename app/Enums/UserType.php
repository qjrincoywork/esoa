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

    /**
     * The user types whose data access is driven by account/branch mappings.
     *
     * Mirrors the row-level rule in {@see \App\Models\User::scopedAccountPairs()}: these
     * are the only types for which a row in `user_accounts` means anything, so the
     * mapping UI and its validation both read the list from here rather than repeating it.
     *
     * @return array<int, int>
     */
    public static function mappable(): array
    {
        return [self::ACCOUNT_BRANCH_ADMIN, self::GROUP_ACCOUNT_ADMIN];
    }

    /**
     * Whether the given type may hold account/branch mappings at all.
     *
     * @param int|string|null $value
     */
    public static function allowsAccountMapping($value): bool
    {
        return in_array((int) $value, self::mappable(), true);
    }

    /**
     * Whether the given type may hold more than one account/branch mapping.
     *
     * Only a group account admin spans several accounts; an account/branch admin is
     * scoped to a single pair.
     *
     * @param int|string|null $value
     */
    public static function allowsMultipleAccounts($value): bool
    {
        return (int) $value === self::GROUP_ACCOUNT_ADMIN;
    }

    /**
     * How many account/branch mappings the given type may hold.
     *
     * @param int|string|null $value
     * @return int|null Null when unlimited; 0 when the type is not mappable at all.
     */
    public static function accountMappingLimit($value): ?int
    {
        return match (true) {
            self::allowsMultipleAccounts($value) => null,
            self::allowsAccountMapping($value) => 1,
            default => 0,
        };
    }
}
