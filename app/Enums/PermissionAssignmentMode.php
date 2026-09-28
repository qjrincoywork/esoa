<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * How a set of permissions is applied to a user's direct permissions.
 *
 * Bulk assignment spans users whose direct permissions differ, so replacing them
 * outright is not always what is wanted: GIVE and REVOKE let one set be added to or
 * taken from every selected user while leaving the rest of their grants alone.
 * Only direct grants are touched — permissions a user inherits through a role are
 * managed on the role.
 */
final class PermissionAssignmentMode extends Enum
{
    /** Replace the user's direct permissions with exactly the submitted set. */
    public const SYNC = 'sync';

    /** Add the submitted permissions, keeping those already granted. */
    public const GIVE = 'give';

    /** Remove the submitted permissions, keeping the others. */
    public const REVOKE = 'revoke';

    /**
     * Map a mode to its human-readable label.
     */
    public static function label($value): string
    {
        return match ($value) {
            self::SYNC => 'Replace',
            self::GIVE => 'Add',
            self::REVOKE => 'Remove',
            default => '',
        };
    }

    /**
     * Explain what applying the selection in this mode does, for the form's hint.
     */
    public static function description($value): string
    {
        return match ($value) {
            self::SYNC => 'The selected permissions will replace the direct permissions of every selected user.',
            self::GIVE => 'The selected permissions will be added to every selected user; existing ones are kept.',
            self::REVOKE => 'The selected permissions will be removed from every selected user; the others are kept.',
            default => '',
        };
    }

    /**
     * Whether the mode needs at least one permission to mean anything — an empty
     * sync clears every direct grant, but an empty add or remove does nothing.
     */
    public static function requiresSelection($value): bool
    {
        return $value !== self::SYNC;
    }

    /**
     * Return every mode as {value, name, description} for the bulk form's selector.
     *
     * @return array<array{value:string,name:string,description:string}>
     */
    public static function list(): array
    {
        return array_map(fn (string $value): array => [
            'value' => $value,
            'name' => self::label($value),
            'description' => self::description($value),
        ], [self::SYNC, self::GIVE, self::REVOKE]);
    }
}
