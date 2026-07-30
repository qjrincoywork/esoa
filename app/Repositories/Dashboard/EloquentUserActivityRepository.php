<?php

namespace App\Repositories\Dashboard;

use App\Contracts\Dashboard\UserActivityRepository;
use App\Enums\DataScope;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Helpers\SqlDatabase;
use App\Models\AccountPayment;
use App\Models\Concern;
use App\Models\User;
use App\Repositories\Dashboard\Concerns\BuildsDashboardSoaQueries;
use App\Support\Dashboard\DashboardContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SQL Server implementation of the per-user reporting read model.
 *
 * Users are not all attributed the same way (see {@see DataScope}), and asking the database
 * once per user would turn a report into N queries. Instead the invoices are aggregated
 * twice — once per uploader and once per account/branch — and those buckets are folded onto
 * each user in memory: two scans regardless of how many users are reported, plus one HMS
 * lookup covering every broker at once.
 *
 * Concerns and payments stay attributed by ownership: a concern is raised by a person, not
 * by an account.
 */
class EloquentUserActivityRepository implements UserActivityRepository
{
    use BuildsDashboardSoaQueries;

    /** Branch key standing in for "no branch" (invoices billed to the account itself). */
    private const NO_BRANCH = "\0";

    /**
     * {@inheritDoc}
     */
    public function breakdown(DashboardContext $context, int $limit): array
    {
        // Fail closed: concerns and payments are not account-scoped, so only the staff
        // roles allowed to read across users may ever reach this aggregation.
        if (!$context->canViewUserReports()) {
            return [];
        }

        // Reporting on one person needs that person's aggregate, not a breakdown of
        // everyone — the common case when a reader clicks a user stays cheap.
        if ($context->targetUser !== null) {
            return $this->singleUserBreakdown($context, $context->targetUser);
        }

        $owned = $this->invoicesByOwner($context);
        $accounts = $this->invoicesByAccount($context);
        $concerns = $this->relatedActivity($context, Concern::query());
        $payments = $this->relatedActivity($context, AccountPayment::query());

        $users = $this->reportableUsers(
            $context,
            array_unique(array_merge(array_keys($owned), array_keys($concerns), array_keys($payments))),
        );

        $agentAccounts = $this->agentAccountCodes($users);
        $targetId = $context->targetUserId();
        $rows = [];

        foreach ($users as $user) {
            $scope = DataScope::forUser($user);

            $invoices = match ($scope) {
                DataScope::ASSIGNED_ACCOUNTS => $this->sumPairs($accounts, $user->scopedAccountPairs()),
                DataScope::AGENT_ACCOUNTS => $this->sumAccounts(
                    $accounts,
                    $agentAccounts[$user->userDetail?->agent_code] ?? []
                ),
                default => $owned[$user->id] ?? $this->emptyMetrics(),
            };

            $concernCount = $concerns[$user->id]['count'] ?? 0;
            $paymentCount = $payments[$user->id]['count'] ?? 0;

            // Idle users are noise in a ranking — except the one being reported on, whose
            // row must stay visible so an empty result reads as "none" rather than "gone".
            if (
                $user->id !== $targetId
                && $invoices['count'] === 0
                && $concernCount === 0
                && $paymentCount === 0
            ) {
                continue;
            }

            $rows[] = [
                'user_id' => (int) $user->id,
                'scope' => $scope,
                'invoice_count' => $invoices['count'],
                'billed_amount' => $invoices['billed'],
                'outstanding_amount' => $invoices['outstanding'],
                'concern_count' => $concernCount,
                'payment_count' => $paymentCount,
                'last_activity_at' => $this->latest(
                    $this->latest($invoices['last_activity_at'], $concerns[$user->id]['last_activity_at'] ?? null),
                    $payments[$user->id]['last_activity_at'] ?? null,
                ),
                'user' => $user,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['invoice_count'], $b['billed_amount'], $b['concern_count'] + $b['payment_count']]
                <=> [$a['invoice_count'], $a['billed_amount'], $a['concern_count'] + $a['payment_count']];
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function selectableUsers(int $limit): Collection
    {
        return User::query()
            ->with('userDetail')
            ->orderBy('username')
            ->limit($limit)
            ->get();
    }

    /**
     * The report for a single subject: one invoice aggregate through that user's own
     * attribution, plus their concerns and payments.
     *
     * The row is returned even when everything is zero — an empty table would read as
     * "this user is gone" rather than "this user has nothing in the selected period".
     *
     * @return array<int, array<string, mixed>>
     */
    private function singleUserBreakdown(DashboardContext $context, User $user): array
    {
        $row = $this->applyPeriod($this->visibleSlice($context), $context)
            ->attributedTo($user)
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS invoice_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status <> ? THEN amount ELSE 0 END), 0) AS outstanding_amount'
                . ', MAX(created_at) AS last_activity_at',
                [SoaStatus::PAID]
            )
            ->first();

        $invoices = $this->metrics($row);
        $concerns = $this->relatedActivity($context, Concern::query())[$user->id] ?? null;
        $payments = $this->relatedActivity($context, AccountPayment::query())[$user->id] ?? null;

        return [[
            'user_id' => (int) $user->id,
            'scope' => DataScope::forUser($user),
            'invoice_count' => $invoices['count'],
            'billed_amount' => $invoices['billed'],
            'outstanding_amount' => $invoices['outstanding'],
            'concern_count' => $concerns['count'] ?? 0,
            'payment_count' => $payments['count'] ?? 0,
            'last_activity_at' => $this->latest(
                $this->latest($invoices['last_activity_at'], $concerns['last_activity_at'] ?? null),
                $payments['last_activity_at'] ?? null,
            ),
            'user' => $user,
        ]];
    }

    /**
     * Users the report should list: everyone with activity in the slice, plus everyone whose
     * data is attributed by account (they never appear in an uploader aggregate). Narrowed
     * to a single person when the dashboard is reporting on one.
     *
     * @param array<int, int> $activeUserIds
     * @return Collection<int, User>
     */
    private function reportableUsers(DashboardContext $context, array $activeUserIds): Collection
    {
        if ($context->targetUser !== null) {
            return collect([$context->targetUser]);
        }

        return User::query()
            ->with(['userDetail', 'userAccounts', 'roles'])
            ->where(function (Builder $query) use ($activeUserIds) {
                $query
                    ->when($activeUserIds !== [], fn (Builder $q) => $q->whereIn('id', $activeUserIds))
                    ->orWhereHas('userAccounts')
                    ->orWhereHas('userDetail', fn ($detail) => $detail->whereNotNull('agent_code'));
            })
            ->get();
    }

    /**
     * Invoice volume and value per uploader.
     *
     * @return array<int, array<string, mixed>>
     */
    private function invoicesByOwner(DashboardContext $context): array
    {
        $rows = $this->applyPeriod($this->visibleSlice($context), $context)
            ->toBase()
            ->selectRaw(
                'user_id'
                . ', COUNT(*) AS invoice_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status <> ? THEN amount ELSE 0 END), 0) AS outstanding_amount'
                . ', MAX(created_at) AS last_activity_at',
                [SoaStatus::PAID]
            )
            ->groupBy('user_id')
            ->get();

        $owned = [];
        foreach ($rows as $row) {
            $owned[(int) $row->user_id] = $this->metrics($row);
        }

        return $owned;
    }

    /**
     * Invoice volume and value per account/branch — the buckets folded onto the users whose
     * data is attributed by assignment.
     *
     * @return array<string, array<string, array<string, mixed>>> account code => branch key => metrics
     */
    private function invoicesByAccount(DashboardContext $context): array
    {
        $rows = $this->applyPeriod($this->visibleSlice($context), $context)
            ->toBase()
            ->selectRaw(
                'account_code, branch_code'
                . ', COUNT(*) AS invoice_count'
                . ', COALESCE(SUM(amount), 0) AS billed_amount'
                . ', COALESCE(SUM(CASE WHEN status <> ? THEN amount ELSE 0 END), 0) AS outstanding_amount'
                . ', MAX(created_at) AS last_activity_at',
                [SoaStatus::PAID]
            )
            ->groupBy('account_code', 'branch_code')
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $branch = ($row->branch_code === null || $row->branch_code === '')
                ? self::NO_BRANCH
                : (string) $row->branch_code;

            $buckets[(string) $row->account_code][$branch] = $this->metrics($row);
        }

        return $buckets;
    }

    /**
     * Resolve the account codes of every broker in one HMS round trip, keyed by agent code.
     *
     * @param Collection<int, User> $users
     * @return array<string, array<int, string>>
     */
    private function agentAccountCodes(Collection $users): array
    {
        $agentCodes = $users
            ->filter(static fn (User $user): bool => DataScope::forUser($user) === DataScope::AGENT_ACCOUNTS)
            ->map(static fn (User $user) => $user->userDetail?->agent_code)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($agentCodes === []) {
            return [];
        }

        try {
            return (new SqlDatabase(Server::HMS))->getAccountCodesByAgentCodes($agentCodes);
        } catch (\Throwable $e) {
            // A broker row degrades to zero invoices rather than taking the report down.
            Log::warning('Dashboard agent account lookup failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Sum the buckets matching a user's account/branch assignments. A pair without a branch
     * covers every branch of that account.
     *
     * @param array<string, array<string, array<string, mixed>>> $buckets
     * @param array<int, array{account_code: string, branch_code: string|null}> $pairs
     * @return array<string, mixed>
     */
    private function sumPairs(array $buckets, array $pairs): array
    {
        $total = $this->emptyMetrics();

        foreach ($pairs as $pair) {
            $account = $buckets[$pair['account_code']] ?? null;

            if ($account === null) {
                continue;
            }

            if ($pair['branch_code'] === null) {
                foreach ($account as $metrics) {
                    $total = $this->addMetrics($total, $metrics);
                }
                continue;
            }

            if (isset($account[$pair['branch_code']])) {
                $total = $this->addMetrics($total, $account[$pair['branch_code']]);
            }
        }

        return $total;
    }

    /**
     * Sum every branch of the given account codes (broker attribution).
     *
     * @param array<string, array<string, array<string, mixed>>> $buckets
     * @param array<int, string> $accountCodes
     * @return array<string, mixed>
     */
    private function sumAccounts(array $buckets, array $accountCodes): array
    {
        return $this->sumPairs(
            $buckets,
            array_map(
                static fn (string $code): array => ['account_code' => $code, 'branch_code' => null],
                $accountCodes
            )
        );
    }

    /**
     * Count rows of a user-owned model (concerns, account payments) per user.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @return array<int, array{count: int, last_activity_at: string|null}>
     */
    private function relatedActivity(DashboardContext $context, Builder $query): array
    {
        $filter = $context->filter;

        $rows = $query
            ->when(
                $filter->hasDateRange(),
                static fn (Builder $builder) => $builder->whereBetween('created_at', [$filter->from, $filter->to])
            )
            ->when(
                $context->targetUserId(),
                static fn (Builder $builder, int $userId) => $builder->where('user_id', $userId)
            )
            ->toBase()
            ->selectRaw('user_id, COUNT(*) AS row_count, MAX(created_at) AS last_activity_at')
            ->groupBy('user_id')
            ->get();

        $activity = [];
        foreach ($rows as $row) {
            $activity[(int) $row->user_id] = [
                'count' => (int) $row->row_count,
                'last_activity_at' => $row->last_activity_at ? (string) $row->last_activity_at : null,
            ];
        }

        return $activity;
    }

    /**
     * Normalize one aggregate row into the metric shape used throughout this repository.
     *
     * @return array<string, mixed>
     */
    private function metrics(object $row): array
    {
        return [
            'count' => (int) $row->invoice_count,
            'billed' => (float) $row->billed_amount,
            'outstanding' => (float) $row->outstanding_amount,
            'last_activity_at' => $row->last_activity_at ? (string) $row->last_activity_at : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(): array
    {
        return ['count' => 0, 'billed' => 0.0, 'outstanding' => 0.0, 'last_activity_at' => null];
    }

    /**
     * @param array<string, mixed> $total
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function addMetrics(array $total, array $metrics): array
    {
        return [
            'count' => $total['count'] + $metrics['count'],
            'billed' => $total['billed'] + $metrics['billed'],
            'outstanding' => $total['outstanding'] + $metrics['outstanding'],
            'last_activity_at' => $this->latest($total['last_activity_at'], $metrics['last_activity_at']),
        ];
    }

    /**
     * The later of two nullable timestamps.
     */
    private function latest(?string $current, ?string $candidate): ?string
    {
        if ($current === null || $candidate === null) {
            return $current ?? $candidate;
        }

        return strtotime($candidate) > strtotime($current) ? $candidate : $current;
    }
}
