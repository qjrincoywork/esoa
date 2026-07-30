<?php

namespace App\Repositories\Dashboard;

use App\Contracts\Dashboard\SoaMetricsRepository;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use App\Models\Soa;
use App\Repositories\Dashboard\Concerns\BuildsDashboardSoaQueries;
use App\Support\Dashboard\DashboardContext;
use App\Support\Dashboard\DashboardFilter;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * SQL Server implementation of the billing-invoice read model.
 *
 * Each widget is served by exactly one aggregate query (conditional SUM/CASE instead of
 * one COUNT per bucket), so the whole dashboard costs a handful of round trips no matter
 * how many buckets are displayed. Aging predicates come from {@see SoaAging::sqlPredicate()}
 * so the cards, the charts and the SOA list can never disagree about what "past due" means.
 */
class EloquentSoaMetricsRepository implements SoaMetricsRepository
{
    use BuildsDashboardSoaQueries;

    /**
     * {@inheritDoc}
     */
    public function summary(DashboardContext $context): array
    {
        $paid = SoaStatus::PAID;
        [$pastDue, $pastDueBindings] = $this->pastDueExpression();

        // Placeholders are appended in the order they appear in the statement — SQL Server
        // binds positionally, so the order here is part of the query, not decoration.
        $bindings = array_merge(
            [$paid, $paid],
            $pastDueBindings,
            $pastDueBindings,
            [$paid],
        );

        $row = $this->baseQuery($context)
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS invoice_count'
                . ', COUNT(DISTINCT account_code) AS account_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) AS collected_amount'
                . ', COALESCE(SUM(CASE WHEN status <> ? THEN amount ELSE 0 END), 0) AS outstanding_amount'
                . ", COALESCE(SUM(CASE WHEN {$pastDue} THEN 1 ELSE 0 END), 0) AS past_due_count"
                . ", COALESCE(SUM(CASE WHEN {$pastDue} AND status <> ? THEN amount ELSE 0 END), 0) AS past_due_outstanding_amount",
                $bindings
            )
            ->first();

        return [
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'account_count' => (int) ($row->account_count ?? 0),
            'billed_amount' => (float) ($row->billed_amount ?? 0),
            'collected_amount' => (float) ($row->collected_amount ?? 0),
            'outstanding_amount' => (float) ($row->outstanding_amount ?? 0),
            'past_due_count' => (int) ($row->past_due_count ?? 0),
            'past_due_outstanding_amount' => (float) ($row->past_due_outstanding_amount ?? 0),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function agingBuckets(DashboardContext $context): array
    {
        $values = SoaAging::getValues();
        $selects = [];
        $bindings = [];

        foreach ($values as $index => $value) {
            [$expression, $predicateBindings] = SoaAging::sqlPredicate((int) $value);

            $selects[] = "COALESCE(SUM(CASE WHEN {$expression} THEN 1 ELSE 0 END), 0) AS bucket_{$index}_count";
            $bindings = array_merge($bindings, $predicateBindings);

            $selects[] = "COALESCE(SUM(CASE WHEN {$expression} THEN amount ELSE 0 END), 0) AS bucket_{$index}_amount";
            $bindings = array_merge($bindings, $predicateBindings);
        }

        $row = $this->baseQuery($context)
            ->toBase()
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        return array_map(static fn (int $index, $value): array => [
            'type' => 'aging',
            'value' => (int) $value,
            'count' => (int) ($row->{"bucket_{$index}_count"} ?? 0),
            'amount' => (float) ($row->{"bucket_{$index}_amount"} ?? 0),
        ], array_keys($values), $values);
    }

    /**
     * {@inheritDoc}
     */
    public function statusBuckets(DashboardContext $context): array
    {
        $rows = $this->baseQuery($context)
            ->toBase()
            ->selectRaw('status, COUNT(*) AS bucket_count, COALESCE(SUM(amount), 0) AS bucket_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return array_map(static fn ($value): array => [
            'type' => 'status',
            'value' => (int) $value,
            'count' => (int) ($rows->get($value)->bucket_count ?? 0),
            'amount' => (float) ($rows->get($value)->bucket_amount ?? 0),
        ], SoaStatus::getValues());
    }

    /**
     * {@inheritDoc}
     */
    public function timeSeries(DashboardContext $context): array
    {
        $periodExpression = $context->filter->granularity() === DashboardFilter::GRANULARITY_DAY
            ? 'CAST(created_at AS DATE)'
            : 'DATEFROMPARTS(YEAR(created_at), MONTH(created_at), 1)';

        $rows = $this->baseQuery($context)
            ->toBase()
            ->selectRaw(
                "{$periodExpression} AS period"
                . ', COUNT(*) AS invoice_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) AS collected_amount',
                [SoaStatus::PAID]
            )
            ->groupByRaw($periodExpression)
            ->get()
            ->keyBy(static fn ($row): string => Carbon::parse($row->period)->format('Y-m-d'));

        $series = [];
        foreach ($this->periods($context, $rows) as $key => $label) {
            $row = $rows->get($key);

            $series[] = [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($row->invoice_count ?? 0),
                'billed' => (float) ($row->billed_amount ?? 0),
                'collected' => (float) ($row->collected_amount ?? 0),
            ];
        }

        return $series;
    }

    /**
     * {@inheritDoc}
     */
    public function topAccounts(DashboardContext $context, int $limit): array
    {
        return $this->baseQuery($context)
            ->toBase()
            ->selectRaw(
                'account_code'
                . ', COUNT(*) AS invoice_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status <> ? THEN amount ELSE 0 END), 0) AS outstanding_amount',
                [SoaStatus::PAID]
            )
            ->groupBy('account_code')
            ->orderBy('outstanding_amount', 'desc')
            ->orderBy('billed_amount', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn ($row): array => [
                'account_code' => (string) $row->account_code,
                'count' => (int) $row->invoice_count,
                'billed_amount' => (float) $row->billed_amount,
                'outstanding_amount' => (float) $row->outstanding_amount,
            ])
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function dataWindow(DashboardContext $context): ?array
    {
        $row = $this->scopedQuery($context)
            ->toBase()
            ->selectRaw('MIN(created_at) AS first_at, MAX(created_at) AS last_at')
            ->first();

        if (empty($row?->first_at) || empty($row?->last_at)) {
            return null;
        }

        return [
            'first_at' => (string) $row->first_at,
            'last_at' => (string) $row->last_at,
        ];
    }

    /**
     * Everything the viewer may read about the reported subject, but not narrowed to the
     * selected period.
     *
     * Separating this from {@see baseQuery()} is what lets {@see dataWindow()} answer "when
     * does this data actually exist" — the question a reader has when a period filter turns
     * every widget to zero.
     */
    private function scopedQuery(DashboardContext $context): Builder
    {
        return $this->visibleSlice($context)->attributedTo($context->targetUser);
    }

    /**
     * Every metric starts here: the viewer's row-level boundary and the reported subject
     * first, then the selected period, so all widgets agree.
     *
     * With the default "all time" filter no bill-date bound is applied at all, which is
     * what makes these totals equal {@see Soa::agingCountsPastDue()} — the counts behind
     * the SOA module dashboard cards.
     */
    private function baseQuery(DashboardContext $context): Builder
    {
        return $this->applyPeriod($this->scopedQuery($context), $context);
    }

    /**
     * Buckets to plot: the selected range when there is one, otherwise the window the data
     * itself occupies.
     *
     * The unbounded window is read off the grouped rows already in memory — their keys are
     * exactly the periods that contain invoices — rather than costing a second MIN/MAX scan.
     *
     * @param \Illuminate\Support\Collection<string, object> $rows Grouped rows keyed by period.
     * @return array<string, string> period key => display label
     */
    private function periods(DashboardContext $context, $rows): array
    {
        if ($context->filter->hasDateRange()) {
            return $context->filter->periods();
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $keys = $rows->keys();

        return $context->filter->periodsBetween(
            CarbonImmutable::parse($keys->min()),
            CarbonImmutable::parse($keys->max()),
        );
    }

    /**
     * SQL predicate matching every invoice in a past-due aging bucket.
     *
     * Built by OR-ing the bucket predicates rather than re-writing "due_date < today", so
     * the past-due KPI is the sum of the past-due bars by construction and cannot drift
     * from {@see SoaAging} if a bucket is ever added or re-cut.
     *
     * @return array{0: string, 1: array<int, int>} [expression, bindings]
     */
    private function pastDueExpression(): array
    {
        $parts = [];
        $bindings = [];

        foreach (SoaAging::getValues() as $value) {
            if (!SoaAging::isPastDue((int) $value)) {
                continue;
            }

            [$expression, $predicateBindings] = SoaAging::sqlPredicate((int) $value);
            $parts[] = "({$expression})";
            $bindings = array_merge($bindings, $predicateBindings);
        }

        return ['(' . implode(' OR ', $parts) . ')', $bindings];
    }
}
