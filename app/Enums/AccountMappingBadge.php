<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The badges an account/branch mapping row carries on the user mapping screen.
 *
 *  - Account / Branch: what the row maps — a whole account (every branch of it), or
 *    one branch. Always shown, on both the available and the mapped panel.
 *  - Expired:          the account — a branch's own account, for a branch — is past its
 *    expiry date ({@see AccountStanding::isExpired()}).
 *  - No members:       HMS records no cardholder against the account or branch.
 *
 * This enum is the single source of truth for those badges: {@see forRow()} decides
 * which apply, and {@see label()} and {@see color()} present them, so the client renders
 * what it is sent and keeps no copy of the rules or the colors.
 */
final class AccountMappingBadge extends Enum
{
    public const ACCOUNT = AccountDirectoryScope::ACCOUNT;
    public const BRANCH = AccountDirectoryScope::BRANCH;
    public const EXPIRED = AccountStanding::EXPIRED;
    public const NO_MEMBERS = 'no_members';

    /**
     * Map a badge to its display label.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::ACCOUNT => 'Account',
            self::BRANCH => 'Branch',
            self::EXPIRED => AccountStanding::label(AccountStanding::EXPIRED),
            self::NO_MEMBERS => 'No members',
            default => 'Unknown',
        };
    }

    /**
     * Semantic color utility classes (background / text, light and dark) for this
     * badge. Layout is left to the consuming badge, as with {@see AccountStanding::color()},
     * whose expired colors are reused so a lapsed account reads alike on every screen.
     *
     * @param  string  $value
     */
    public static function color($value): string
    {
        return match ($value) {
            self::ACCOUNT => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
            self::BRANCH => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
            self::EXPIRED => AccountStanding::color(AccountStanding::EXPIRED),
            self::NO_MEMBERS => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        };
    }

    /**
     * Everything a client needs to render one badge, so none of it is restated there.
     *
     * @param  string  $value
     * @return array{value: string, label: string, color: string}
     */
    public static function present($value): array
    {
        return [
            'value' => $value,
            'label' => self::label($value),
            'color' => self::color($value),
        ];
    }

    /**
     * The kind of a mapping row: a blank branch code covers every branch of the
     * account, which is the same distinction `branch_code` carries in `user_accounts`.
     */
    public static function kindOf(?string $branchCode): string
    {
        return trim((string) $branchCode) === '' ? self::ACCOUNT : self::BRANCH;
    }

    /**
     * The badges of one mapping row: its kind, plus whichever status badges apply.
     *
     * A null member count means the count was not looked up, which is not the same as
     * none, so it raises no badge.
     *
     * @param  string  $kind  {@see ACCOUNT} or {@see BRANCH}.
     * @param  \DateTimeInterface|string|null  $accountExpiry  The (branch's) account's `ac_expiry`.
     * @param  int|null  $memberCount  Cardholders recorded against the account or branch.
     * @return array{kind_badge: array{value: string, label: string, color: string}, status_badges: array<int, array{value: string, label: string, color: string}>}
     */
    public static function forRow(string $kind, $accountExpiry, ?int $memberCount): array
    {
        $statuses = array_keys(array_filter([
            self::EXPIRED => AccountStanding::isExpired($accountExpiry),
            self::NO_MEMBERS => $memberCount === 0,
        ]));

        return [
            'kind_badge' => self::present($kind),
            'status_badges' => array_map(self::present(...), $statuses),
        ];
    }
}
