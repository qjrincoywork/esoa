<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;
use Illuminate\Support\Facades\{ DB, Log };
use phpDocumentor\Reflection\Types\Integer;

use function Psy\debug;

/**
 * Enum representation of bill type values.
 *
 * @extends Enum<int>
 */
final class BillType extends Enum
{

    /* Basic HMO */
    public const INITIAL_NEW = 1;
    public const RENEWAL = 2;
    public const SUBSEQUENT = 3;
    public const MISCELLANEOUS = 4;
    public const PREMIUM_ADJUSTMENT = 5;
    public const FOLLOW_UP_CANCELLED = 6;
    /* Basic */
    public const REPLENISHMENT = 7;
    public const SECURITY_DEPOSIT = 8;
    public const MEDCOLL = 9;
    public const ECU = 10;
    public const PRE_EMPLOYMENT = 11;
    public const ADDITIONAL_ENROLLEE = 12;
    public const ACCESS_FEE = 13;
    public const OTHER_FEES = 14;

    public const LOST_ID = 15;
    public const AMENDMENT = 16;
    public const REINSTATEMENT = 17;

    public const BDO_BILLBACK = 18;
    public const BILLBACK_ARRANGEMENT = 19;
    public const NURSE_OVERTIME = 20;
    public const SPONSORSHIP_ON_NEWSPAPER = 21;
    public const MIGRATED = 22;
    /**
     * Get the human readable label for a given bill type value.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::INITIAL_NEW => 'Initial / New',
            self::RENEWAL => 'Renewal',
            self::SUBSEQUENT => 'Subsequent',
            self::MISCELLANEOUS => 'Miscellaneous',
            self::PREMIUM_ADJUSTMENT => 'Premium Adjustment',
            self::FOLLOW_UP_CANCELLED => 'Follow-up Cancelled',
            self::ADDITIONAL_ENROLLEE => 'Additional Enrollee',
            self::LOST_ID => 'Lost ID',
            self::ECU => 'ECU',
            self::PRE_EMPLOYMENT => 'Pre-Employment',
            self::ACCESS_FEE => 'Access Fee',
            self::OTHER_FEES => 'Other Fee',
            self::AMENDMENT => 'Amendment',
            self::REINSTATEMENT => 'Reinstatement',
            self::REPLENISHMENT => 'Replenishment',
            self::SECURITY_DEPOSIT => 'Security Deposit / Health Fund / Revolving Fund',
            self::MEDCOLL => 'Medical Collectible',
            self::BDO_BILLBACK => 'BDO Billback',
            self::BILLBACK_ARRANGEMENT => 'Billback Arrangement',
            self::NURSE_OVERTIME => 'Nurse Overtime',
            self::SPONSORSHIP_ON_NEWSPAPER => 'Sponsorship on Newspaper',
        };
    }

    public static function oldValue($value)
    {
        $value = strtoupper(trim(preg_replace('/\s+/', ' ', $value)));

        return match ($value) {
            'INITIAL/NEW' => self::INITIAL_NEW,
            'RENEWAL' => self::RENEWAL,
            'SUBSEQUENT' => self::SUBSEQUENT,
            'MISCELLANEOUS' => self::MISCELLANEOUS,
            'PREMIUM_ADJUSTMENT' => self::PREMIUM_ADJUSTMENT,
            'FOLLOW UP CANCELLED' => self::FOLLOW_UP_CANCELLED,
            'REPLENISHMENT' => self::REPLENISHMENT,
            'SECURITY DEPOSIT / HEALTH FUND / REVOLVING FUND' => self::SECURITY_DEPOSIT,
            'MEDCOLL' => self::MEDCOLL,
            'ECU' => self::ECU,
            'PRE-EMPLOYMENT' => self::PRE_EMPLOYMENT,
            'ADDITIONAL ENROLLEE' => self::ADDITIONAL_ENROLLEE,
            'ACCESS FEE' => self::ACCESS_FEE,
            'OTHER FEE' => self::OTHER_FEES,
            'AMENDMENT' => self::AMENDMENT,
            'REINSTATEMENT' => self::REINSTATEMENT,
            'LOST ID' => self::LOST_ID,
            'BDO BILLBACK' => self::BDO_BILLBACK,
            'BILLBACK ARRANGEMENT' => self::BILLBACK_ARRANGEMENT,
            'NURSE OVERTIME' => self::NURSE_OVERTIME,
            'SPONSORSHIP ON NEWSPAPER AD' => self::SPONSORSHIP_ON_NEWSPAPER,
            default => self::MIGRATED, // or whatever fallback you want
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
            ['value' => self::SUBSEQUENT, 'name' => self::label(self::SUBSEQUENT)],
            ['value' => self::MISCELLANEOUS, 'name' => self::label(self::MISCELLANEOUS)],
            ['value' => self::PREMIUM_ADJUSTMENT, 'name' => self::label(self::PREMIUM_ADJUSTMENT)],
            ['value' => self::ADDITIONAL_ENROLLEE, 'name' => self::label(self::ADDITIONAL_ENROLLEE)],
            ['value' => self::FOLLOW_UP_CANCELLED, 'name' => self::label(self::FOLLOW_UP_CANCELLED)],
            ['value' => self::INITIAL_NEW, 'name' => self::label(self::INITIAL_NEW)],
            ['value' => self::LOST_ID, 'name' => self::label(self::LOST_ID)],
            ['value' => self::ACCESS_FEE, 'name' => self::label(self::ACCESS_FEE)],
            ['value' => self::OTHER_FEES, 'name' => self::label(self::OTHER_FEES)],
            ['value' => self::AMENDMENT, 'name' => self::label(self::AMENDMENT)],
            ['value' => self::RENEWAL, 'name' => self::label(self::RENEWAL)],
            ['value' => self::REINSTATEMENT, 'name' => self::label(self::REINSTATEMENT)],
            ['value' => self::REPLENISHMENT, 'name' => self::label(self::REPLENISHMENT)],
            ['value' => self::SECURITY_DEPOSIT, 'name' => self::label(self::SECURITY_DEPOSIT)],
            ['value' => self::MEDCOLL, 'name' => self::label(self::MEDCOLL)],
            ['value' => self::ECU, 'name' => self::label(self::ECU)],
            ['value' => self::PRE_EMPLOYMENT, 'name' => self::label(self::PRE_EMPLOYMENT)],
            ['value' => self::BDO_BILLBACK, 'name' => self::label(self::BDO_BILLBACK)],
            ['value' => self::BILLBACK_ARRANGEMENT, 'name' => self::label(self::BILLBACK_ARRANGEMENT)],
            ['value' => self::NURSE_OVERTIME, 'name' => self::label(self::NURSE_OVERTIME)],
            ['value' => self::SPONSORSHIP_ON_NEWSPAPER, 'name' => self::label(self::SPONSORSHIP_ON_NEWSPAPER)],
        ];
    }
}
