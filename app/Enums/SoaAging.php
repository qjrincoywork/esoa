<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class SoaAging extends Enum
{
    public const DUE_IN_LESS_THAN_1_DAY = 0;
    public const DUE_IN_30_DAYS = 30;
    public const DUE_IN_60_DAYS = 60;
    public const DUE_IN_90_DAYS = 90;
    public const DUE_IN_120_DAYS = 120;
    public const DUE_IN_OVER_120_DAYS = 121;

    public static function label($value): string
    {
        return match ($value) {
            self::DUE_IN_LESS_THAN_1_DAY => 'Due in less than 1 day',
            self::DUE_IN_30_DAYS => 'Due in less than 30 days',
            self::DUE_IN_60_DAYS => 'Due in less than 60 days',
            self::DUE_IN_90_DAYS => 'Due in less than 90 days',
            self::DUE_IN_120_DAYS => 'Due in less than 120 days',
            self::DUE_IN_OVER_120_DAYS => 'Due in more than 120 days',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::DUE_IN_LESS_THAN_1_DAY => 'p-3 rounded-lg bg-red-500/20 border-red-500/30',
            self::DUE_IN_30_DAYS => 'p-3 rounded-lg bg-orange-500/20 border-orange-500/30',
            self::DUE_IN_60_DAYS => 'p-3 rounded-lg bg-amber-500/20 border-amber-500/30',
            self::DUE_IN_90_DAYS => 'p-3 rounded-lg bg-lime-500/20 border-lime-500/30',
            self::DUE_IN_120_DAYS => 'p-3 rounded-lg bg-emerald-500/20 border-emerald-500/30',
            self::DUE_IN_OVER_120_DAYS => 'p-3 rounded-lg bg-green-500/20 border-green-500/30',
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
            self::DUE_IN_LESS_THAN_1_DAY => [null, 0],
            self::DUE_IN_30_DAYS => [1, 30],
            self::DUE_IN_60_DAYS => [31, 60],
            self::DUE_IN_90_DAYS => [61, 90],
            self::DUE_IN_120_DAYS => [91, 120],
            self::DUE_IN_OVER_120_DAYS => [121, null],
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::DUE_IN_LESS_THAN_1_DAY, 'name' => self::label(self::DUE_IN_LESS_THAN_1_DAY)],
            ['value' => self::DUE_IN_30_DAYS, 'name' => self::label(self::DUE_IN_30_DAYS)],
            ['value' => self::DUE_IN_60_DAYS, 'name' => self::label(self::DUE_IN_60_DAYS)],
            ['value' => self::DUE_IN_90_DAYS, 'name' => self::label(self::DUE_IN_90_DAYS)],
            ['value' => self::DUE_IN_120_DAYS, 'name' => self::label(self::DUE_IN_120_DAYS)],
            ['value' => self::DUE_IN_OVER_120_DAYS, 'name' => self::label(self::DUE_IN_OVER_120_DAYS)],
        ];
    }
}
