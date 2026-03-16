<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Enum representation of bill type values.
 *
 * @extends Enum<int>
 */
final class BillType extends Enum
{
    public const PREMIUM_ADJUSTMENT = 1;
    public const ADDITIONAL_ENROLLEE = 2;
    public const FOLLOW_UP_CANCELLED = 3;
    public const INITIAL_NEW = 4;
    public const LOST_ID = 5;
    public const OTHER_FEES = 6;
    public const AMENDMENT = 7;
    public const RENEWAL = 8;
    public const REINSTATEMENT = 9;
    public const REPLENISHMENT = 10;
    public const MEDCOLL = 11;

    /**
     * Get the human readable label for a given bill type value.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::PREMIUM_ADJUSTMENT => 'Premium Adjustment',
            self::ADDITIONAL_ENROLLEE => 'Additional Enrollee',
            self::FOLLOW_UP_CANCELLED => 'Follow-up Cancelled',
            self::INITIAL_NEW => 'Initial / New',
            self::LOST_ID => 'Lost ID',
            self::OTHER_FEES => 'Other Fees',
            self::AMENDMENT => 'Amendment',
            self::RENEWAL => 'Renewal',
            self::REINSTATEMENT => 'Reinstatement',
            self::REPLENISHMENT => 'Replenishment',
            self::MEDCOLL => 'Medical Collectible',
        };
    }

    /**
     * Get all bill types as an array of value/label pairs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::PREMIUM_ADJUSTMENT, 'name' => self::label(self::PREMIUM_ADJUSTMENT)],
            ['value' => self::ADDITIONAL_ENROLLEE, 'name' => self::label(self::ADDITIONAL_ENROLLEE)],
            ['value' => self::FOLLOW_UP_CANCELLED, 'name' => self::label(self::FOLLOW_UP_CANCELLED)],
            ['value' => self::INITIAL_NEW, 'name' => self::label(self::INITIAL_NEW)],
            ['value' => self::LOST_ID, 'name' => self::label(self::LOST_ID)],
            ['value' => self::OTHER_FEES, 'name' => self::label(self::OTHER_FEES)],
            ['value' => self::AMENDMENT, 'name' => self::label(self::AMENDMENT)],
            ['value' => self::RENEWAL, 'name' => self::label(self::RENEWAL)],
            ['value' => self::REINSTATEMENT, 'name' => self::label(self::REINSTATEMENT)],
            ['value' => self::REPLENISHMENT, 'name' => self::label(self::REPLENISHMENT)],
            ['value' => self::MEDCOLL, 'name' => self::label(self::MEDCOLL)],
        ];
    }
}
