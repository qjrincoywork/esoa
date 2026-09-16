<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Type of a remark in the legacy eSOA conversation, from `remarks.rem_to`.
 *
 * The old chat only ever singled out 1; 0, 2 and NULL all occur in the data and were
 * every one of them captioned as a SOA concern, so {@see label()} keeps that fallback
 * rather than inventing labels the previous system never showed.
 */
final class OldRemarkType extends Enum
{
    public const SOA_CONCERN = 0;
    public const PAYMENT_DETAILS = 1;

    /**
     * Map a legacy remark type to its human-readable label.
     *
     * @param  int|string|null  $value
     * @return string
     */
    public static function label($value): string
    {
        return (int) $value === self::PAYMENT_DETAILS
            ? 'For Payment Details / Adjustments'
            : 'SOA Concern';
    }
}
