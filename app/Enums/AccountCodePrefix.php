<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Leading segments of an HMS account code that classify the account.
 *
 * The constants are named after the literal prefix rather than a guessed meaning, so
 * the list stays truthful to what HMS stores; what each group is *used for* is decided
 * by the helpers below rather than by the constant names.
 */
final class AccountCodePrefix extends Enum
{
    public const IN = 'IN-';
    public const GR = 'GR-';
    public const FM = 'FM-';
    public const AA = 'AA-';
    public const AT = 'AT-';
    public const TP = 'TP-';
    public const CP = 'CP-';
    public const SP = 'SP-';

    /**
     * Prefixes never offered when mapping accounts and branches onto a user.
     *
     * These accounts exist in the HMS directory but are not ones a user is given
     * access to, so both the account picker and the branch picker filter them out —
     * a branch is excluded by the account it belongs to, not by its own code.
     *
     * @return array<int, string>
     */
    public static function excludedFromUserAccess(): array
    {
        return [self::IN, self::GR, self::FM];
    }

    /**
     * Prefixes a user's access can actually be granted on.
     *
     * The complement of {@see excludedFromUserAccess()}, derived rather than listed
     * again so the two can never drift: a prefix added above is offered for filtering
     * the moment it is not excluded.
     *
     * @return array<int, string>
     */
    public static function assignableToUserAccess(): array
    {
        return array_values(array_diff(self::getValues(), self::excludedFromUserAccess()));
    }

    /**
     * Return the assignable prefixes as {value, name} option arrays for select inputs.
     *
     * The prefix is its own label: the constants are deliberately not given invented
     * meanings, and the codes themselves are what an administrator reads off the
     * screen, so naming one "AA-" is both truthful and recognisable.
     *
     * Only assignable prefixes are offered, because the screens that filter by prefix
     * already hide the excluded classes — an option for one would always return
     * nothing.
     *
     * @return array<array{value:string,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'name' => $value],
            self::assignableToUserAccess()
        );
    }

    /**
     * The prefix an account code carries, or null when it starts with none of them.
     *
     * HMS holds a handful of codes that match no known prefix, so this reports what is
     * there rather than forcing every code into a group.
     *
     * @param  string|null  $accountCode
     */
    public static function of(?string $accountCode): ?string
    {
        $accountCode = trim((string) $accountCode);

        if ($accountCode === '') {
            return null;
        }

        foreach (self::getValues() as $prefix) {
            if (str_starts_with($accountCode, $prefix)) {
                return $prefix;
            }
        }

        return null;
    }
}
