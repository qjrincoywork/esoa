<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Which side of the legacy eSOA conversation a remark came from.
 *
 * `remarks.rem_isVC` is 1 when ValueCare posted the message — `rem_by` then holds an
 * HMS login that resolves to a staff name — and 0 when the client did, where `rem_by`
 * already holds the company name. The oldest rows leave it NULL; those carry HMS
 * logins and system text ("SOA uploaded."), so an absent flag reads as ValueCare,
 * which is also how the old chat grouped it (`rem_isVC == 1 || rem_isVC == null`).
 */
final class OldRemarkSide extends Enum
{
    public const CLIENT = 0;
    public const VALUE_CARE = 1;

    /**
     * Resolve the raw `rem_isVC` flag to a side, treating anything but an explicit 0
     * as ValueCare so no legacy row is dropped for carrying an unexpected value.
     *
     * @param  int|string|null  $flag
     * @return int
     */
    public static function fromFlag($flag): int
    {
        return $flag !== null && (int) $flag === self::CLIENT
            ? self::CLIENT
            : self::VALUE_CARE;
    }

    /**
     * Map a side to its human-readable label.
     *
     * @param  int  $value
     * @return string
     */
    public static function label($value): string
    {
        return (int) $value === self::CLIENT ? 'Client' : 'ValueCare';
    }
}
