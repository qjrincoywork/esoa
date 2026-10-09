<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Whether a user can be given a set of accounts/branches from the unmapped listing.
 *
 *  - Eligible:         the user holds none of them and has room for all.
 *  - Partially mapped: the user already holds some; only the rest would be added.
 *  - Already mapped:   the user holds every one of them, so there is nothing to add.
 *  - Limit reached:    adding them would take the user past their type's cap
 *                      ({@see UserType::accountMappingLimit()}).
 *  - Not mappable:     the user's type is not scoped by mappings at all
 *                      ({@see UserType::allowsAccountMapping()}).
 *
 * The single source of truth for that decision: the user picker shows it, and the
 * assignment is validated by it, so the two can never disagree about who may be picked.
 */
final class MappingEligibility extends Enum
{
    public const ELIGIBLE = 'eligible';
    public const PARTIALLY_MAPPED = 'partially_mapped';
    public const ALREADY_MAPPED = 'already_mapped';
    public const LIMIT_REACHED = 'limit_reached';
    public const NOT_MAPPABLE = 'not_mappable';

    /**
     * Map an eligibility to its display label.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::ELIGIBLE => 'Eligible',
            self::PARTIALLY_MAPPED => 'Partly mapped',
            self::ALREADY_MAPPED => 'Already mapped',
            self::LIMIT_REACHED => 'Limit reached',
            self::NOT_MAPPABLE => 'Not mappable',
            default => 'Unknown',
        };
    }

    /**
     * Semantic color utility classes (background / text, light and dark), in the same
     * vocabulary as {@see AccountMappingBadge::color()}.
     *
     * @param  string  $value
     */
    public static function color($value): string
    {
        return match ($value) {
            self::ELIGIBLE => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            self::PARTIALLY_MAPPED => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
            self::LIMIT_REACHED => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        };
    }

    /**
     * Everything a client needs to render and act on one eligibility.
     *
     * @param  string  $value
     * @return array{value: string, label: string, color: string, assignable: bool}
     */
    public static function present($value): array
    {
        return [
            'value' => $value,
            'label' => self::label($value),
            'color' => self::color($value),
            'assignable' => self::isAssignable($value),
        ];
    }

    /**
     * Whether picking the user would map anything: there is something left to add and
     * room to add it.
     *
     * @param  string  $value
     */
    public static function isAssignable($value): bool
    {
        return in_array($value, [self::ELIGIBLE, self::PARTIALLY_MAPPED], true);
    }

    /**
     * Decide a user's eligibility for a set of accounts/branches.
     *
     * @param  int|string|null  $userType  The user's `user_details.type`.
     * @param  int  $mappedCount  Distinct pairs the user holds now.
     * @param  int  $heldCount  How many of the requested pairs the user already holds.
     * @param  int  $requestedCount  How many distinct pairs are being assigned.
     */
    public static function resolve($userType, int $mappedCount, int $heldCount, int $requestedCount): string
    {
        if (!UserType::allowsAccountMapping($userType)) {
            return self::NOT_MAPPABLE;
        }

        $adding = max(0, $requestedCount - $heldCount);

        if ($adding === 0) {
            return self::ALREADY_MAPPED;
        }

        $limit = UserType::accountMappingLimit($userType);

        if ($limit !== null && $mappedCount + $adding > $limit) {
            return self::LIMIT_REACHED;
        }

        return $heldCount > 0 ? self::PARTIALLY_MAPPED : self::ELIGIBLE;
    }
}
