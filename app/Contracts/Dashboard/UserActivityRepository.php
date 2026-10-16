<?php

namespace App\Contracts\Dashboard;

use App\Support\Dashboard\DashboardContext;
use Illuminate\Support\Collection;

/**
 * Read model behind the per-user reporting section of the dashboard.
 *
 * Split from {@see SoaMetricsRepository} on purpose: only privileged staff ever reach
 * this data, so the widgets that do not need it never depend on it.
 */
interface UserActivityRepository
{
    /**
     * Activity per user inside the filtered range, ranked by invoice volume within each
     * attribution scope and capped at `$limitPerScope` rows per scope.
     *
     * Invoices are attributed by {@see \App\Enums\DataScope} — uploads for staff, assigned
     * accounts for account and group admins, agent accounts for brokers — so a user who
     * never uploads anything still gets the figures that concern them. Concerns, payments
     * and invoice actions (viewed, emailed, updated) stay attributed by ownership. Active
     * users are listed even when idle. Each row carries the resolved {@see User} so the
     * caller renders names without a second lookup.
     *
     * @return array<int, array{
     *     user_id: int,
     *     scope: int,
     *     invoice_count: int,
     *     billed_amount: float,
     *     outstanding_amount: float,
     *     concern_count: int,
     *     payment_count: int,
     *     viewed_count: int,
     *     emailed_count: int,
     *     updated_count: int,
     *     last_activity_at: string|null,
     *     user: \App\Models\User
     * }>
     */
    public function breakdown(DashboardContext $context, int $limitPerScope): array;

    /**
     * Users selectable in the dashboard's "viewing" filter.
     *
     * @return Collection<int, \App\Models\User>
     */
    public function selectableUsers(int $limit): Collection;
}
