<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\SoaMetricsRepository;
use App\Contracts\Dashboard\UserActivityRepository;
use App\Http\Resources\Dashboard\DataWindowResource;
use App\Http\Resources\Dashboard\MetricBucketResource;
use App\Http\Resources\Dashboard\SummaryResource;
use App\Http\Resources\Dashboard\TimeSeriesResource;
use App\Http\Resources\Dashboard\TopAccountResource;
use App\Http\Resources\Dashboard\UserOptionResource;
use App\Http\Resources\Dashboard\UserReportResource;
use App\Enums\Server;
use App\Helpers\SqlDatabase;
use App\Models\Account;
use App\Support\Dashboard\DashboardContext;
use App\Support\Dashboard\DashboardFilter;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Assembles the dashboard payload from the metric read models.
 *
 * The service owns composition only — it never writes SQL (that is the repositories') and
 * never formats values (that is the resources'). Adding a widget therefore means adding a
 * repository method plus a resource, not editing the ones already here.
 *
 * Every prop is returned as a closure so Inertia evaluates only what a given request
 * actually asks for: a filter change reloads the widgets partially and the untouched ones
 * cost nothing.
 */
class DashboardReportService
{
    /** Bars in the "largest balance" ranking. */
    public const TOP_ACCOUNTS_LIMIT = 8;

    /** Rows in the per-user activity report. */
    public const USER_REPORT_LIMIT = 25;

    /** Users offered in the "viewing" filter. */
    public const USER_OPTIONS_LIMIT = 500;

    public function __construct(
        private readonly SoaMetricsRepository $soaMetrics,
        private readonly UserActivityRepository $userActivity,
    ) {
    }

    /**
     * Inertia props for the dashboard page.
     *
     * Resources are resolved to plain arrays instead of being handed to Inertia as
     * Responsable objects: that keeps every prop a flat structure on the client rather
     * than a `{ data: … }` envelope the page would have to unwrap.
     *
     * @return array<string, \Closure|\Inertia\DeferProp>
     */
    public function props(DashboardContext $context): array
    {
        return [
            'filters' => fn (): array => $context->filter->toArray(),
            'filter_options' => fn (): array => $this->filterOptions($context),
            'data_window' => fn (): ?array => $this->dataWindow($context),
            'summary' => fn (): array => (new SummaryResource($this->soaMetrics->summary($context)))->resolve(),
            'aging_buckets' => fn (): array => MetricBucketResource::collection(
                $this->soaMetrics->agingBuckets($context)
            )->resolve(),
            'status_buckets' => fn (): array => MetricBucketResource::collection(
                $this->soaMetrics->statusBuckets($context)
            )->resolve(),
            'billing_trend' => fn (): array => (new TimeSeriesResource(
                $this->soaMetrics->timeSeries($context),
                $context->filter->granularity()
            ))->resolve(),
            'top_accounts' => fn (): array => TopAccountResource::collection(
                $this->topAccounts($context)
            )->resolve(),
            // The per-user report is the most expensive widget and the last one read, so it
            // is deferred: the page paints on the metric props and Inertia fetches this
            // straight after mount. Non-privileged viewers never see the section, so they
            // must not pay for the extra round trip either.
            'user_reports' => $context->canViewUserReports()
                ? Inertia::defer(
                    fn (): array => UserReportResource::collection($this->userReports($context))->resolve()
                )
                : fn (): ?array => null,
            'can_view_user_reports' => fn (): bool => $context->canViewUserReports(),
        ];
    }

    /**
     * Options backing the filter row: range presets always, the user selector only for the
     * staff roles allowed to read across users.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(DashboardContext $context): array
    {
        return [
            'presets' => DashboardFilter::presets(),
            'users' => $context->canViewUserReports()
                ? UserOptionResource::collection(
                    $this->userActivity->selectableUsers(self::USER_OPTIONS_LIMIT)
                )->resolve()
                : [],
        ];
    }

    /**
     * The window the reported data occupies, or null when the scope holds no invoices at
     * all (a brand new account, say) — the page then says "no data yet" rather than
     * blaming the selected period.
     *
     * @return array<string, mixed>|null
     */
    private function dataWindow(DashboardContext $context): ?array
    {
        $window = $this->soaMetrics->dataWindow($context);

        return $window === null ? null : (new DataWindowResource($window))->resolve();
    }

    /**
     * Ranking rows enriched with their account name, resolved in one bulk lookup.
     *
     * The locally imported directory answers first (no cross-server hop); HMS is only
     * consulted for the codes it could not resolve, and a failing lookup degrades to the
     * account code rather than breaking the widget.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topAccounts(DashboardContext $context): array
    {
        $rows = $this->soaMetrics->topAccounts($context, self::TOP_ACCOUNTS_LIMIT);

        if ($rows === []) {
            return [];
        }

        $codes = array_column($rows, 'account_code');
        $names = Account::query()->whereIn('code', $codes)->pluck('name', 'code');
        $missing = array_values(array_diff($codes, $names->keys()->all()));

        if ($missing !== []) {
            try {
                $names = $names->merge(
                    (new SqlDatabase(Server::HMS))->getAccountNamesByCodes($missing)
                );
            } catch (\Throwable $e) {
                Log::warning('Dashboard account name lookup failed: ' . $e->getMessage());
            }
        }

        return array_map(static fn (array $row): array => $row + [
            'account_name' => $names[$row['account_code']] ?? null,
        ], $rows);
    }

    /**
     * Per-user activity rows.
     *
     * The read model already resolves each row's user (with detail and roles eager loaded)
     * because it needs the model to classify the attribution scope, so nothing is looked up
     * again here. Soft-deleted users never enter the report: the read model builds its user
     * list through `User::query()`, which applies the soft-delete scope.
     *
     * @return array<int, array<string, mixed>>
     */
    private function userReports(DashboardContext $context): array
    {
        return $this->userActivity->breakdown($context, self::USER_REPORT_LIMIT);
    }
}
