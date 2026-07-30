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
     * Activity per user inside the filtered range, ordered by invoice volume.
     *
     * Invoices are attributed by {@see \App\Enums\DataScope} — uploads for staff, assigned
     * accounts for account and group admins, agent accounts for brokers — so a user who
     * never uploads anything still gets the figures that concern them. Concerns and
     * payments stay attributed by ownership. Each row carries the resolved {@see User} so
     * the caller renders names without a second lookup.
     *
     * @return array<int, array{
     *     user_id: int,
     *     scope: int,
     *     invoice_count: int,
     *     billed_amount: float,
     *     outstanding_amount: float,
     *     concern_count: int,
     *     payment_count: int,
     *     last_activity_at: string|null,
     *     user: \App\Models\User
     * }>
     */
    public function breakdown(DashboardContext $context, int $limit): array;

    /**
     * Users selectable in the dashboard's "viewing" filter.
     *
     * @return Collection<int, \App\Models\User>
     */
    public function selectableUsers(int $limit): Collection;
}
