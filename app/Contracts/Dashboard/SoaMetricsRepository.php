<?php

namespace App\Contracts\Dashboard;

use App\Support\Dashboard\DashboardContext;

/**
 * Read model behind the billing-invoice widgets of the dashboard.
 *
 * The service layer depends on this contract rather than on Eloquent, so the aggregation
 * strategy (Eloquent today, a reporting view or cached snapshot tomorrow) can be swapped
 * by rebinding the interface in the service provider.
 *
 * Every method receives the full {@see DashboardContext} and is responsible for applying
 * the viewer's row-level boundary — no implementation may return unscoped rows.
 */
interface SoaMetricsRepository
{
    /**
     * Headline totals for the KPI row.
     *
     * Implementations must keep these reconcilable with the bucket methods below:
     * `invoice_count` equals the sum of either bucket set, `billed_amount` the sum of their
     * amounts, and `past_due_count` the sum of the past-due aging buckets (all statuses —
     * aging is a due-date dimension, not a settlement one). `past_due_outstanding_amount`
     * is the money still owed inside those buckets.
     *
     * @return array{
     *     invoice_count: int,
     *     account_count: int,
     *     billed_amount: float,
     *     collected_amount: float,
     *     outstanding_amount: float,
     *     past_due_count: int,
     *     past_due_outstanding_amount: float
     * }
     */
    public function summary(DashboardContext $context): array;

    /**
     * Invoice count and value per {@see \App\Enums\SoaAging} bucket, in enum order.
     *
     * @return array<int, array{type: string, value: int, count: int, amount: float}>
     */
    public function agingBuckets(DashboardContext $context): array;

    /**
     * Invoice count and value per {@see \App\Enums\SoaStatus}, in enum order.
     *
     * @return array<int, array{type: string, value: int, count: int, amount: float}>
     */
    public function statusBuckets(DashboardContext $context): array;

    /**
     * Billed vs collected value per period, zero-filled across the whole range.
     *
     * @return array<int, array{key: string, label: string, count: int, billed: float, collected: float}>
     */
    public function timeSeries(DashboardContext $context): array;

    /**
     * The period the reported data actually spans, ignoring the selected range.
     *
     * Lets the page explain an empty result ("no invoices in this window; data runs from …")
     * instead of showing a wall of zeros.
     *
     * @return array{first_at: string, last_at: string}|null Null when the scope has no rows.
     */
    public function dataWindow(DashboardContext $context): ?array;

    /**
     * The accounts carrying the largest outstanding balance.
     *
     * @return array<int, array{
     *     account_code: string,
     *     count: int,
     *     billed_amount: float,
     *     outstanding_amount: float
     * }>
     */
    public function topAccounts(DashboardContext $context, int $limit): array;
}
