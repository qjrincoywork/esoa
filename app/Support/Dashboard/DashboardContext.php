<?php

namespace App\Support\Dashboard;

use App\Models\User;

/**
 * Who is asking ({@see $viewer}), what they are asking for ({@see $filter}) and, when the
 * report is narrowed to one person, who that person is ({@see $targetUser}).
 *
 * Passing them around as one object keeps the security scope attached to every metric
 * query — a repository cannot read the filter without also having the viewer whose
 * row-level boundary it must apply — and gives the repositories the target *model* rather
 * than a bare id, which is what lets them attribute data by the user's role instead of by
 * `soas.user_id` (see {@see \App\Enums\DataScope}).
 */
final class DashboardContext
{
    /**
     * Staff roles allowed to break the per-user reporting boundary. Kept in sync with the
     * roles that may list soft-deleted SOAs in {@see \App\Models\Soa::listQuery()}.
     *
     * @var array<int, string>
     */
    private const USER_REPORT_ROLES = ['admin'];

    public function __construct(
        public readonly User $viewer,
        public readonly DashboardFilter $filter,
        public readonly ?User $targetUser = null,
    ) {
    }

    /**
     * Build the context for a request, resolving the selected user when the viewer is
     * allowed to scope by user.
     *
     * The lookup lives here rather than in the controller so the privilege rule and the
     * resolution it guards cannot drift apart. Soft-deleted users never resolve
     * ({@see User::query()} applies the soft-delete scope), which — together with the
     * `exists` rule on the request — keeps deleted people out of the reports.
     */
    public static function for(User $viewer, DashboardFilter $filter): self
    {
        $targetUser = null;

        if ($filter->userId !== null && self::mayViewUserReports($viewer)) {
            $targetUser = User::query()
                ->with(['userDetail', 'userAccounts', 'roles'])
                ->find($filter->userId);
        }

        return new self($viewer, $filter, $targetUser);
    }

    /**
     * May this viewer see activity broken down per user (the superadmin report section)?
     */
    public static function mayViewUserReports(User $viewer): bool
    {
        return $viewer->hasAnyRole(
            array_merge([config('vc.superadmin')], self::USER_REPORT_ROLES)
        );
    }

    /**
     * Instance form of {@see mayViewUserReports()}.
     */
    public function canViewUserReports(): bool
    {
        return self::mayViewUserReports($this->viewer);
    }

    /**
     * The user the metrics are narrowed to, or null for "everyone the viewer can see".
     *
     * Fails closed: a tenant-scoped user who hand-crafts a `user_id` query parameter never
     * gets a target, so the parameter can neither widen nor redirect their own scope.
     */
    public function targetUserId(): ?int
    {
        return $this->targetUser?->id;
    }

    /**
     * True when a user filter was requested but could not be resolved (deleted between the
     * request being validated and served). The metrics must then return nothing rather
     * than silently falling back to the unfiltered set.
     */
    public function hasUnresolvedTarget(): bool
    {
        return $this->filter->userId !== null
            && $this->canViewUserReports()
            && $this->targetUser === null;
    }
}
