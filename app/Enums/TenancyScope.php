<?php

namespace App\Enums;

use App\Models\User;
use BenSampo\Enum\Enum;

/**
 * How much of the estate a user is allowed to see.
 *
 * The row-level boundary used to be re-derived at every query, as a run of role-name
 * comparisons ending in "and otherwise, no restriction". That default is what let a user
 * the list did not name — including one carrying no role at all — read every tenant's
 * rows. The decision now lives here, once, and the absence of a match resolves to
 * {@see NONE} rather than to everything.
 *
 * Every scope reads this enum and treats {@see NONE} as "no rows", so a role created
 * tomorrow is unprivileged until it is deliberately given a branch here. Adding a role
 * to the estate can no longer widen a query by omission.
 *
 * @see \App\Models\Soa::scopeVisibleTo()
 * @see \App\Helpers\SqlDatabase::applyCholderAccountFilters()
 * @see \App\Helpers\SqlDatabase::applyAccountDirectoryFilter()
 */
final class TenancyScope extends Enum
{
    /** No rows. The default for any user this enum does not recognise. */
    public const NONE = 0;

    /** The whole estate — full-access staff. */
    public const ALL = 1;

    /** Only the account/branch pairs in the user's `user_accounts`. */
    public const ASSIGNED_ACCOUNTS = 2;

    /** Only the accounts under the user's agent code. */
    public const AGENT_ACCOUNTS = 3;

    /**
     * Roles that see the whole estate.
     *
     * The superadmin name comes from config because the rest of the application reads it
     * from there too; the other two are fixed staff roles.
     *
     * @return array<int, string>
     */
    public static function fullAccessRoles(): array
    {
        return [config('vc.superadmin'), 'admin', 'billing_admin'];
    }

    /**
     * Roles scoped to the accounts assigned to them in `user_accounts`.
     *
     * @return array<int, string>
     */
    public static function assignedAccountRoles(): array
    {
        return ['account_branch_admin', 'group_account_admin'];
    }

    /**
     * Classify what the given user may see.
     *
     * Order matters: the widest scope wins, so a staff member who also carries a tenant
     * role is not accidentally narrowed. Anything unrecognised — an unknown role, a role
     * created through the admin screens, or no role at all — resolves to {@see NONE}.
     *
     * @param  User|null  $user  Should carry its `roles` relation to avoid a query per call.
     */
    public static function forUser(?User $user): int
    {
        if (!$user) {
            return self::NONE;
        }

        if ($user->hasAnyRole(self::fullAccessRoles())) {
            return self::ALL;
        }

        if ($user->hasRole('broker')) {
            return self::AGENT_ACCOUNTS;
        }

        if ($user->hasAnyRole(self::assignedAccountRoles())) {
            return self::ASSIGNED_ACCOUNTS;
        }

        return self::NONE;
    }

    /**
     * Map a scope to how it is described in the interface.
     *
     * @param  int  $value
     */
    public static function label($value): string
    {
        return match ((int) $value) {
            self::ALL => 'All accounts',
            self::ASSIGNED_ACCOUNTS => 'Assigned accounts',
            self::AGENT_ACCOUNTS => 'Agent accounts',
            default => 'No access',
        };
    }

    /**
     * Return every scope as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (int $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues(),
        );
    }
}
