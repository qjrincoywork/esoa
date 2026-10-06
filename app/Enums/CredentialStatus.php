<?php

namespace App\Enums;

use BenSampo\Enum\Enum;
use Carbon\CarbonInterface;

/**
 * Where a user's login credentials stand.
 *
 * Derived, never stored: it follows from when credentials were sent and whether the
 * temporary password they carried is still in force. Changing or resetting the password
 * clears `temporary_password_expires_at`, so a sent user with no expiry has replaced it.
 * {@see \App\Models\User::scopeCredentialStatus()} expresses the same rules as a query,
 * so the list badge, its filter and the reports agree.
 */
final class CredentialStatus extends Enum
{
    /** Credentials have not been emailed yet (the user is unverified). */
    public const NOT_SENT = 0;

    /** Sent, and the user is still on the temporary password, which has not expired. */
    public const TEMPORARY = 1;

    /** Sent, but the temporary password expired before it was replaced. */
    public const EXPIRED = 2;

    /** Sent, and the user has replaced the temporary password with their own. */
    public const UPDATED = 3;

    /**
     * Resolve the status from the credential timestamps.
     */
    public static function resolve(?CarbonInterface $sentAt, ?CarbonInterface $temporaryExpiresAt): int
    {
        return match (true) {
            $sentAt === null => self::NOT_SENT,
            $temporaryExpiresAt === null => self::UPDATED,
            $temporaryExpiresAt->isPast() => self::EXPIRED,
            default => self::TEMPORARY,
        };
    }

    /**
     * Map a status to its human-readable label.
     */
    public static function label($value): string
    {
        return match ((int) $value) {
            self::NOT_SENT => 'Not Sent',
            self::TEMPORARY => 'Temporary Password',
            self::EXPIRED => 'Temporary Expired',
            self::UPDATED => 'Password Updated',
            default => '',
        };
    }

    /**
     * Semantic badge classes (background / text), matching the list's other pills.
     */
    public static function color($value): string
    {
        return match ((int) $value) {
            self::NOT_SENT => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            self::TEMPORARY => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::EXPIRED => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            self::UPDATED => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            default => '',
        };
    }

    /**
     * Every status as {value, name} options for the list filter.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (int $value): array => ['value' => $value, 'name' => self::label($value)],
            [self::NOT_SENT, self::TEMPORARY, self::EXPIRED, self::UPDATED]
        );
    }
}
