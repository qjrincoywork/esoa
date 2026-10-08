<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * What the unmapped-directory listing shows: accounts, branches, or both together.
 *
 * Kept apart from {@see AccountDirectoryScope} on purpose. A scope says what one row
 * *is* — every row is an account or a branch, and the detail pane and mapping screens
 * switch on that — whereas "both" only exists as a way of listing them. Folding it into
 * the scope would let a row, or a detail request, claim to be "all".
 *
 * The single views share the scope's values, so a view of one kind and the scope of its
 * rows always read the same.
 */
final class AccountDirectoryView extends Enum
{
    public const ALL = 'all';
    public const ACCOUNT = AccountDirectoryScope::ACCOUNT;
    public const BRANCH = AccountDirectoryScope::BRANCH;

    /**
     * Map a view to the wording used for it in the interface.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::ACCOUNT => 'Accounts',
            self::BRANCH => 'Branches',
            default => 'All',
        };
    }

    /**
     * Return every view as {value, name} option arrays for tabs, the combined view first.
     *
     * @return array<array{value:string,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues()
        );
    }

    /**
     * Resolve a submitted view, falling back to both kinds together — searching one name
     * or code across accounts and branches at once is what the listing is opened for.
     *
     * @param  string|null  $value
     */
    public static function resolve(?string $value): string
    {
        return self::hasValue((string) $value) ? (string) $value : self::ALL;
    }

    /**
     * The kinds of row a view lists ({@see AccountDirectoryScope}).
     *
     * @param  string  $value
     * @return array<int, string>
     */
    public static function scopes($value): array
    {
        return match ($value) {
            self::ACCOUNT => [AccountDirectoryScope::ACCOUNT],
            self::BRANCH => [AccountDirectoryScope::BRANCH],
            default => [AccountDirectoryScope::ACCOUNT, AccountDirectoryScope::BRANCH],
        };
    }

    /**
     * Whether a view lists rows of the given kind.
     *
     * @param  string  $value
     */
    public static function includes($value, string $scope): bool
    {
        return in_array($scope, self::scopes($value), true);
    }
}
