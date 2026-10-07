<?php

namespace App\Enums;

use BenSampo\Enum\Enum;
use Carbon\Carbon;

/**
 * Whether an HMS account is in force, as the unmapped-accounts listing and its detail
 * pane show it (a branch shows its account's):
 *
 *  - Expired:  past its expiry date — whatever the status letter still says.
 *  - Active:   marked active ({@see AccountStatus::ACTIVE}) and not expired.
 *  - Inactive: anything else.
 *
 * This enum is the single source of truth for standing: {@see resolve()} decides it,
 * {@see label()} and {@see color()} present it, and {@see present()} hands all three to
 * the client so no screen keeps its own copy of the rule or the colors.
 */
final class AccountStanding extends Enum
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const EXPIRED = 'expired';

    /**
     * Map a standing to its display label.
     *
     * @param string $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::EXPIRED => 'Expired',
            default => 'Unknown',
        };
    }

    /**
     * Semantic color utility classes (background / text, light and dark) for this
     * standing. Layout (padding, rounding) is left to the consuming badge, so the same
     * colors serve the listing column and the pane heading.
     */
    public static function color($value): string
    {
        return match ($value) {
            self::ACTIVE => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::INACTIVE => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::EXPIRED => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        };
    }

    /**
     * Text-only color for inline facts tied to a standing (e.g. the expiry date beside
     * it), so a lapsed date stands out without a second badge. Empty when nothing needs
     * drawing attention to.
     */
    public static function textColor($value): string
    {
        return match ($value) {
            self::EXPIRED => 'text-red-700 dark:text-red-400',
            default => '',
        };
    }

    /**
     * Decide an account's standing. Expiry outranks the status letter: HMS leaves
     * thousands of accounts marked active long after they lapse, and the expiry date,
     * unlike a guess at what 'E' means, is a fact the record states outright.
     *
     * @param  string|null  $status  The raw `ac_status`.
     * @param  \DateTimeInterface|string|null  $expiry  The raw `ac_expiry`.
     */
    public static function resolve($status, $expiry): string
    {
        return self::resolveFrom(AccountStatus::isActive($status), $expiry);
    }

    /**
     * {@see resolve()} for callers holding an already-folded active flag rather than a
     * status letter (e.g. a branch's account, folded across duplicate account codes).
     *
     * @param  \DateTimeInterface|string|null  $expiry
     */
    public static function resolveFrom(bool $isActive, $expiry): string
    {
        return match (true) {
            self::isExpired($expiry) => self::EXPIRED,
            $isActive => self::ACTIVE,
            default => self::INACTIVE,
        };
    }

    /**
     * Whether an expiry date has passed: expired from the day after it. An account with
     * no expiry date recorded is never treated as expired.
     *
     * @param  \DateTimeInterface|string|null  $expiry
     */
    public static function isExpired($expiry): bool
    {
        return !empty($expiry) && Carbon::parse($expiry)->startOfDay()->lt(Carbon::today());
    }

    /**
     * Everything a client needs to render a standing, so none of it is restated there.
     *
     * @param string $value
     * @return array{value: string, label: string, color: string, text_color: string}
     */
    public static function present($value): array
    {
        return [
            'value' => $value,
            'label' => self::label($value),
            'color' => self::color($value),
            'text_color' => self::textColor($value),
        ];
    }

    /**
     * Selectable options in display order, derived from the declared values (DRY).
     *
     * @return array<int, array{value: string, name: string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues(),
        );
    }
}
