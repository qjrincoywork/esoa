<?php

namespace App\Enums;

use BenSampo\Enum\Enum;
use Carbon\Carbon;

/**
 * SOA aging buckets (mutually exclusive), ordered for dashboard/list display:
 *
 *  - Not Yet Due (Future Remittance): due date beyond the current month.
 *  - Due (Current Month):             due date within the current month and not yet past due.
 *  - Past Due:                        not settled by the due date, split into 30/60/90/120/over-120 day buckets.
 *
 * This enum is the single source of truth for aging: {@see sqlPredicate()} owns the SQL
 * filter used by list/reminder queries, and {@see classify()} mirrors it in PHP for display.
 */
final class SoaAging extends Enum
{
    public const NOT_YET_DUE = 1;
    public const DUE_CURRENT_MONTH = 2;
    public const PAST_DUE_30 = 30;
    public const PAST_DUE_60 = 60;
    public const PAST_DUE_90 = 90;
    public const PAST_DUE_120 = 120;
    public const PAST_DUE_OVER_120 = 121;

    /**
     * Map an aging bucket value to its display label (e.g. "Past Due – 30 Days").
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::NOT_YET_DUE => 'Not Yet Due (Future Remittance)',
            self::DUE_CURRENT_MONTH => 'Due (Current Month)',
            self::PAST_DUE_30 => 'Past Due – 30 Days',
            self::PAST_DUE_60 => 'Past Due – 60 Days',
            self::PAST_DUE_90 => 'Past Due – 90 Days',
            self::PAST_DUE_120 => 'Past Due – 120 Days',
            self::PAST_DUE_OVER_120 => 'Past Due – Over 120 Days',
            default => 'Unknown',
        };
    }

    /**
     * Semantic color utility classes (background / text / border) for this bucket.
     * Layout (padding, rounding) is intentionally left to the consuming template so the
     * same colors can style both dashboard cards and inline aging badges.
     */
    public static function color($value): string
    {
        return match ($value) {
            self::NOT_YET_DUE => 'bg-green-500/20 text-green-600 border-green-500/30',
            self::DUE_CURRENT_MONTH => 'bg-blue-500/20 text-blue-600 border-blue-500/30',
            self::PAST_DUE_30 => 'bg-amber-500/20 text-amber-600 border-amber-500/30',
            self::PAST_DUE_60 => 'bg-orange-500/20 text-orange-600 border-orange-500/30',
            self::PAST_DUE_90 => 'bg-red-500/20 text-red-600 border-red-500/30',
            self::PAST_DUE_120 => 'bg-rose-600/20 text-rose-700 border-rose-600/30',
            self::PAST_DUE_OVER_120 => 'bg-red-700/20 text-red-800 border-red-700/30',
            default => 'bg-gray-500/20 text-gray-600 border-gray-500/30',
        };
    }

    /**
     * SQL predicate (SQL Server) selecting SOAs whose {@code due_date} falls in this bucket.
     * Returns {@code [rawExpression, bindings]} for {@code whereRaw()}.
     *
     * {@code DATEDIFF(day, GETDATE(), due_date)} = days from today until due_date
     * (negative = overdue; e.g. -1 means one day past due).
     *
     * @return array{0: string, 1: array<int, int>}
     */
    public static function sqlPredicate(int $value): array
    {
        return match ($value) {
            self::NOT_YET_DUE => ['CAST(due_date AS DATE) > EOMONTH(GETDATE())', []],
            self::DUE_CURRENT_MONTH => ['CAST(due_date AS DATE) BETWEEN CAST(GETDATE() AS DATE) AND EOMONTH(GETDATE())', []],
            self::PAST_DUE_30 => ['DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', [-30, -1]],
            self::PAST_DUE_60 => ['DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', [-60, -31]],
            self::PAST_DUE_90 => ['DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', [-90, -61]],
            self::PAST_DUE_120 => ['DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?', [-120, -91]],
            self::PAST_DUE_OVER_120 => ['DATEDIFF(day, GETDATE(), due_date) <= ?', [-121]],
        };
    }

    /**
     * PHP mirror of {@see sqlPredicate()} — classify a single due date into its aging bucket.
     * Used to render the per-SOA "Due In" label (list + details pane).
     */
    public static function classify(Carbon $dueDate): int
    {
        $today = Carbon::today();
        $due = $dueDate->copy()->startOfDay();

        if ($due->lessThan($today)) {
            $overdueDays = $due->diffInDays($today);

            return match (true) {
                $overdueDays <= 30 => self::PAST_DUE_30,
                $overdueDays <= 60 => self::PAST_DUE_60,
                $overdueDays <= 90 => self::PAST_DUE_90,
                $overdueDays <= 120 => self::PAST_DUE_120,
                default => self::PAST_DUE_OVER_120,
            };
        }

        return $due->lessThanOrEqualTo($today->copy()->endOfMonth())
            ? self::DUE_CURRENT_MONTH
            : self::NOT_YET_DUE;
    }

    /**
     * SOA list route filtered by this aging bucket (same query as dashboard cards).
     */
    public static function listUrl(int $value): string
    {
        return route('soas.list', ['due_in' => $value]);
    }

    /**
     * Selectable options in display order, derived from the declared values (DRY).
     *
     * @return array<int, array{value: int, name: string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (int $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues(),
        );
    }
}
