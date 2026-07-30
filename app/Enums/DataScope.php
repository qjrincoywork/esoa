<?php

namespace App\Enums;

use App\Models\User;
use BenSampo\Enum\Enum;

/**
 * How a user's billing data is attributed in reports.
 *
 * Not every user *creates* invoices. Staff upload them, so their records are the ones they
 * own; account and group admins never upload anything — the invoices that concern them are
 * the ones billed to the accounts they are assigned to; a broker's are the invoices of the
 * accounts under their agent code. Reporting on `soas.user_id` alone therefore shows a
 * tenant admin an empty dashboard, which is exactly the bug this enum exists to prevent.
 *
 * The classification mirrors the row-level boundary in
 * {@see \App\Models\Soa::scopeVisibleTo()}: "the data attributed to a user" and "the data
 * that user is allowed to see" must describe the same rows.
 */
final class DataScope extends Enum
{
    /** Records the user created (`soas.user_id`) — VC employees and staff roles. */
    public const OWNED_RECORDS = 1;

    /** Invoices billed to the account/branch pairs in the user's `user_accounts`. */
    public const ASSIGNED_ACCOUNTS = 2;

    /** Invoices of every account under the user's agent code (broker). */
    public const AGENT_ACCOUNTS = 3;

    /**
     * Map a scope to the short label shown beside a user in the activity report.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::OWNED_RECORDS => 'Uploads',
            self::ASSIGNED_ACCOUNTS => 'Assigned accounts',
            self::AGENT_ACCOUNTS => 'Agent accounts',
            default => 'Uploads',
        };
    }

    /**
     * Longer description, used where the report explains itself (tooltips, legends).
     *
     * @param int $value
     * @return string
     */
    public static function description($value): string
    {
        return match ($value) {
            self::OWNED_RECORDS => 'Billing invoices this user uploaded',
            self::ASSIGNED_ACCOUNTS => 'Billing invoices of the accounts this user is assigned to',
            self::AGENT_ACCOUNTS => 'Billing invoices of the accounts under this user\'s agent code',
            default => 'Billing invoices this user uploaded',
        };
    }

    /**
     * Classify a user.
     *
     * Roles decide first, because they are what the query scopes key on; the user type is
     * a fallback for accounts whose role assignment has drifted from their detail record.
     *
     * @param User $user Should carry its `roles` and `userDetail` relations to avoid N+1.
     */
    public static function forUser(User $user): int
    {
        if ($user->hasAnyRole(['account_branch_admin', 'group_account_admin'])) {
            return self::ASSIGNED_ACCOUNTS;
        }

        if ($user->hasRole('broker')) {
            return self::AGENT_ACCOUNTS;
        }

        return match ((int) ($user->userDetail?->type ?? 0)) {
            UserType::ACCOUNT_BRANCH_ADMIN, UserType::GROUP_ACCOUNT_ADMIN => self::ASSIGNED_ACCOUNTS,
            UserType::BROKER => self::AGENT_ACCOUNTS,
            default => self::OWNED_RECORDS,
        };
    }

    /**
     * Return all scopes as {value, name} option arrays.
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
