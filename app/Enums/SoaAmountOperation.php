<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Add / deduct operations for SOA amount management (API + activity log).
 *
 * @extends Enum<string>
 */
final class SoaAmountOperation extends Enum
{
    public const ADD = 'add';

    public const DEDUCT = 'deduct';

    /**
     * Map an amount operation to its human-readable label; unknown values pass through unchanged.
     *
     * @param string $value
     * @return string
     */
    public static function label(string $value): string
    {
        return match ($value) {
            self::ADD => 'Add to amount',
            self::DEDUCT => 'Deduct from amount',
            default => $value,
        };
    }

    /** Stored on {@see \App\Models\SoaActivity::$event} for filtering / display. */
    public static function activityEvent(string $value): string
    {
        return match ($value) {
            self::ADD => 'amount_added',
            self::DEDUCT => 'amount_deducted',
            default => 'amount_adjusted',
        };
    }
}
