<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Whether a user has used the credentials they were sent.
 *
 * A user has accessed them once they have signed in since the credentials were sent,
 * or have replaced the temporary password — which cannot be done without them. Only
 * meaningful for users whose credentials were sent; the rules live in
 * {@see \App\Models\User::hasAccessedCredentials()} and, as a query,
 * {@see \App\Models\User::scopeCredentialAccess()}.
 */
final class CredentialAccess extends Enum
{
    public const NOT_ACCESSED = 0;

    public const ACCESSED = 1;

    /**
     * Map an access state to its human-readable label.
     */
    public static function label($value): string
    {
        return match ((int) $value) {
            self::ACCESSED => 'Accessed',
            self::NOT_ACCESSED => 'Not Yet Accessed',
            default => '',
        };
    }

    /**
     * Both states as {value, name} options for the list filter.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::ACCESSED, 'name' => self::label(self::ACCESSED)],
            ['value' => self::NOT_ACCESSED, 'name' => self::label(self::NOT_ACCESSED)],
        ];
    }
}
