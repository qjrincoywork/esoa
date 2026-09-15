<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Which half of the HMS account/branch directory a listing is looking at.
 *
 * An account and a branch are mapped onto a user through the same table
 * ({@see \App\Models\UserAccount}) but are counted, searched and displayed
 * differently — a branch carries the account it belongs to, an account does not.
 * Screens that show one or the other switch on this rather than on a loose string,
 * so the two views can never be confused for one another.
 *
 * Only the two scopes are declared as constants: the reflection behind
 * {@see Enum::getValues()} reads every constant on the class, so a convenience
 * constant such as a default would come back as a third, duplicate member.
 */
final class AccountDirectoryScope extends Enum
{
    public const ACCOUNT = 'account';
    public const BRANCH = 'branch';

    /**
     * Map a scope to the wording used for it in the interface.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::BRANCH => 'Branches',
            default => 'Accounts',
        };
    }

    /**
     * Return every scope as {value, name} option arrays for tabs and select inputs.
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
     * The scope a request falls back to when it names none.
     */
    public static function defaultScope(): string
    {
        return self::ACCOUNT;
    }

    /**
     * Resolve a submitted scope, falling back to {@see defaultScope()} for anything
     * the enum does not know.
     *
     * @param  string|null  $value
     */
    public static function resolve(?string $value): string
    {
        return self::hasValue((string) $value) ? (string) $value : self::defaultScope();
    }
}
