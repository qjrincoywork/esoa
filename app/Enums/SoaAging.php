<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class SoaAging extends Enum
{
    public const PAST_DUE = 0;
    public const DUE_WITHIN_30_DAYS = 30;
    public const DUE_WITHIN_60_DAYS = 60;
    public const DUE_WITHIN_90_DAYS = 90;
    public const DUE_WITHIN_120_DAYS = 120;
    public const DUE_WITHIN_MORE_THAN_120_DAYS = 121;

    public static function label($value): string
    {
        return match ($value) {
            self::PAST_DUE => 'Past Due',
            self::DUE_WITHIN_30_DAYS => 'Due within 30 days',
            self::DUE_WITHIN_60_DAYS => 'Due within 60 days',
            self::DUE_WITHIN_90_DAYS => 'Due within 90 days',
            self::DUE_WITHIN_120_DAYS => 'Due within 120 days',
            self::DUE_WITHIN_MORE_THAN_120_DAYS => 'Due within more than 120 days',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::PAST_DUE => 'p-3 rounded-lg bg-red-500/20 border-red-500/30',
            self::DUE_WITHIN_30_DAYS => 'p-3 rounded-lg bg-orange-500/20 border-orange-500/30',
            self::DUE_WITHIN_60_DAYS => 'p-3 rounded-lg bg-amber-500/20 border-amber-500/30',
            self::DUE_WITHIN_90_DAYS => 'p-3 rounded-lg bg-lime-500/20 border-lime-500/30',
            self::DUE_WITHIN_120_DAYS => 'p-3 rounded-lg bg-emerald-500/20 border-emerald-500/30',
            self::DUE_WITHIN_MORE_THAN_120_DAYS => 'p-3 rounded-lg bg-green-500/20 border-green-500/30',
            default => 'p-3 rounded-lg bg-gray-500/20 border-gray-500/30',
        };
    }

    /**
     * Positive calendar days overdue: {@code DATEDIFF(CURDATE(), due_date)} for {@code due_date} before today.
     * Inclusive [min, max]; max null means >= min (over-120 bucket). [0,0] is due today.
     *
     * @return array{0: int, 1: int|null}
     */
    public static function pastDueDayBucketsRange(int $value): array
    {
        return match ($value) {
            self::PAST_DUE => [null, 0],
            self::DUE_WITHIN_30_DAYS => [1, 30],
            self::DUE_WITHIN_60_DAYS => [31, 60],
            self::DUE_WITHIN_90_DAYS => [61, 90],
            self::DUE_WITHIN_120_DAYS => [91, 120],
            self::DUE_WITHIN_MORE_THAN_120_DAYS => [121, null],
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::PAST_DUE, 'name' => self::label(self::PAST_DUE)],
            ['value' => self::DUE_WITHIN_30_DAYS, 'name' => self::label(self::DUE_WITHIN_30_DAYS)],
            ['value' => self::DUE_WITHIN_60_DAYS, 'name' => self::label(self::DUE_WITHIN_60_DAYS)],
            ['value' => self::DUE_WITHIN_90_DAYS, 'name' => self::label(self::DUE_WITHIN_90_DAYS)],
            ['value' => self::DUE_WITHIN_120_DAYS, 'name' => self::label(self::DUE_WITHIN_120_DAYS)],
            ['value' => self::DUE_WITHIN_MORE_THAN_120_DAYS, 'name' => self::label(self::DUE_WITHIN_MORE_THAN_120_DAYS)],
        ];
    }
}
