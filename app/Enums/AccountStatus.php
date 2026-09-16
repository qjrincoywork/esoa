<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The status HMS records against an account, in `Accounts.ac_status`.
 *
 * Only the active marker is named. HMS also stores 'C' and 'E' against most of the
 * rest, and the obvious readings — cancelled, expired — are a guess this codebase has
 * never needed to make: everywhere it asks about status it asks the same question,
 * "is this account still in force", and 'A' is the documented answer to it. Naming the
 * other letters would put assumptions into the system that nothing verifies.
 *
 * So the question is answered as a boolean, and {@see IsActive} supplies the two
 * options wherever it is offered as a filter.
 */
final class AccountStatus extends Enum
{
    /** An account currently in force. */
    public const ACTIVE = 'A';

    /**
     * Whether a stored status means the account is in force.
     *
     * Anything that is not the active marker — including the null HMS leaves on a
     * couple of hundred rows — is not, which is the reading every caller already had
     * written out in place.
     *
     * @param  string|null  $value  The raw `ac_status`.
     */
    public static function isActive($value): bool
    {
        return $value === self::ACTIVE;
    }
}
