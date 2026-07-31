<?php

namespace App\Support\Dashboard;

use Carbon\CarbonImmutable;

/**
 * Immutable description of the slice of data a dashboard request is asking for.
 *
 * Every dashboard widget reads the same filter instance, which is what guarantees the
 * numbers on the page agree with each other. The value object owns all calendar logic
 * (preset -> range, range -> bucket granularity, the zero-fill period list) so the
 * repositories only ever translate an already-resolved range into SQL.
 *
 * The default is deliberately {@see PRESET_ALL_TIME} — an unbounded range — because the
 * SOA module dashboard ({@see \App\Models\Soa::agingCountsPastDue()}) counts every invoice
 * the viewer can see, and a landing page that silently showed a narrower window would
 * disagree with it. A bounded range is then an explicit choice the reader makes.
 */
final class DashboardFilter
{
    public const PRESET_ALL_TIME = 'all_time';
    public const PRESET_LAST_30_DAYS = 'last_30_days';
    public const PRESET_LAST_90_DAYS = 'last_90_days';
    public const PRESET_YEAR_TO_DATE = 'year_to_date';
    public const PRESET_LAST_12_MONTHS = 'last_12_months';
    public const PRESET_CUSTOM = 'custom';

    public const GRANULARITY_DAY = 'day';
    public const GRANULARITY_MONTH = 'month';

    /** Preset applied when the request carries no (valid) range. */
    public const DEFAULT_PRESET = self::PRESET_ALL_TIME;

    /** Explicit ranges wider than this are clamped so a hand-typed date cannot scan forever. */
    private const MAX_RANGE_MONTHS = 36;

    /** A bounded range up to this many days is bucketed per day; anything longer per month. */
    private const DAILY_BUCKET_MAX_DAYS = 62;

    /** Upper bound on plotted buckets, so an unbounded range cannot blow up the payload. */
    private const MAX_PERIODS = 60;

    /**
     * @param CarbonImmutable|null $from Inclusive lower bound, or null for "no lower bound".
     * @param CarbonImmutable|null $to   Inclusive upper bound, or null for "no upper bound".
     */
    private function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly string $preset,
        public readonly ?int $userId,
        public readonly ?string $accountCode,
    ) {
    }

    /**
     * Build a filter from already-validated request input.
     *
     * A complete `date_from`/`date_to` pair wins and marks the filter as custom; otherwise
     * the named preset (or the default) resolves the range.
     *
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        $rawFrom = $params['date_from'] ?? null;
        $rawTo = $params['date_to'] ?? null;

        if (!empty($rawFrom) && !empty($rawTo)) {
            $preset = self::PRESET_CUSTOM;
            $from = CarbonImmutable::parse($rawFrom)->startOfDay();
            $to = CarbonImmutable::parse($rawTo)->endOfDay();

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
            }

            $earliest = $to->subMonths(self::MAX_RANGE_MONTHS)->startOfDay();
            if ($from->lessThan($earliest)) {
                $from = $earliest;
            }
        } else {
            $preset = self::resolvePreset($params['preset'] ?? null);
            [$from, $to] = self::rangeForPreset($preset);
        }

        $userId = isset($params['user_id']) && $params['user_id'] !== null && $params['user_id'] !== ''
            ? (int) $params['user_id']
            : null;

        $accountCode = isset($params['account_code']) && $params['account_code'] !== ''
            ? (string) $params['account_code']
            : null;

        return new self($from, $to, $preset, $userId, $accountCode);
    }

    /**
     * Selectable range presets in display order, for the filter row.
     *
     * @return array<int, array{value: string, name: string}>
     */
    public static function presets(): array
    {
        return [
            ['value' => self::PRESET_ALL_TIME, 'name' => 'All time'],
            ['value' => self::PRESET_LAST_30_DAYS, 'name' => 'Last 30 days'],
            ['value' => self::PRESET_LAST_90_DAYS, 'name' => 'Last 90 days'],
            ['value' => self::PRESET_YEAR_TO_DATE, 'name' => 'Year to date'],
            ['value' => self::PRESET_LAST_12_MONTHS, 'name' => 'Last 12 months'],
        ];
    }

    /**
     * Does this filter narrow the data by bill date at all?
     *
     * When false the metrics must run unbounded, which is what makes the totals equal the
     * SOA module dashboard's own counts.
     */
    public function hasDateRange(): bool
    {
        return $this->from !== null && $this->to !== null;
    }

    /**
     * Bucket size for the time series: daily for short bounded ranges, monthly otherwise.
     */
    public function granularity(): string
    {
        if (!$this->hasDateRange()) {
            return self::GRANULARITY_MONTH;
        }

        return $this->from->diffInDays($this->to) <= self::DAILY_BUCKET_MAX_DAYS
            ? self::GRANULARITY_DAY
            : self::GRANULARITY_MONTH;
    }

    /**
     * Every bucket of the selected range, keyed by the string the repository returns, so a
     * period with no invoices still renders as a zero on the line (no phantom gaps).
     *
     * Unbounded filters have no calendar of their own — the repository passes the window it
     * found in the data to {@see periodsBetween()} instead.
     *
     * @return array<string, string> period key => display label
     */
    public function periods(): array
    {
        return $this->hasDateRange()
            ? $this->periodsBetween($this->from, $this->to)
            : [];
    }

    /**
     * Bucket list for an arbitrary window, at this filter's granularity.
     *
     * Truncated to the most recent {@see MAX_PERIODS} buckets: a chart cannot usefully show
     * more, and an unbounded range must not scale its payload with the age of the data.
     *
     * @return array<string, string> period key => display label
     */
    public function periodsBetween(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $isDaily = $this->granularity() === self::GRANULARITY_DAY;
        $cursor = $isDaily ? $start->startOfDay() : $start->startOfMonth();
        $last = $isDaily ? $end->startOfDay() : $end->startOfMonth();

        $periods = [];
        while ($cursor->lessThanOrEqualTo($last)) {
            $periods[$cursor->format('Y-m-d')] = $isDaily
                ? $cursor->format('M j')
                : $cursor->format('M Y');

            $cursor = $isDaily ? $cursor->addDay() : $cursor->addMonth();
        }

        return count($periods) > self::MAX_PERIODS
            ? array_slice($periods, -self::MAX_PERIODS, null, true)
            : $periods;
    }

    /**
     * Serializable form, used to hydrate the filter row back on the client.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'date_from' => $this->from?->toDateString(),
            'date_to' => $this->to?->toDateString(),
            'user_id' => $this->userId,
            'account_code' => $this->accountCode,
            'granularity' => $this->granularity(),
            'label' => $this->label(),
        ];
    }

    /**
     * Human-readable description of the active range (e.g. "Aug 1, 2025 – Jul 30, 2026").
     */
    public function label(): string
    {
        if (!$this->hasDateRange()) {
            return 'all billing invoices';
        }

        return $this->from->format('M j, Y') . ' – ' . $this->to->format('M j, Y');
    }

    /**
     * Coerce arbitrary input to a known preset, falling back to the default.
     */
    private static function resolvePreset(mixed $preset): string
    {
        $allowed = array_column(self::presets(), 'value');

        return is_string($preset) && in_array($preset, $allowed, true)
            ? $preset
            : self::DEFAULT_PRESET;
    }

    /**
     * Resolve a preset into an inclusive [start of day, end of day] range, or [null, null]
     * for the unbounded "all time" preset.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function rangeForPreset(string $preset): array
    {
        if ($preset === self::PRESET_ALL_TIME) {
            return [null, null];
        }

        $today = CarbonImmutable::today();

        $from = match ($preset) {
            self::PRESET_LAST_30_DAYS => $today->subDays(29),
            self::PRESET_LAST_90_DAYS => $today->subDays(89),
            self::PRESET_YEAR_TO_DATE => $today->startOfYear(),
            default => $today->subMonthsNoOverflow(11)->startOfMonth(),
        };

        return [$from->startOfDay(), $today->endOfDay()];
    }
}
