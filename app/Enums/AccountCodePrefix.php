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
}
