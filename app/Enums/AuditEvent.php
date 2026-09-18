<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The kinds of change recorded in the audit trail.
 *
 * These are the values written to `activity_log.event`: the four spatie writes for
 * model events, plus the batch-upload outcomes, which are recorded by hand because
 * they are not model events at all. A rejected batch in particular changes nothing —
 * that is exactly why it is worth a trail, since otherwise an attempt that failed
 * validation leaves no trace of having been made.
 *
 * They are listed here so the filter offers exactly what can occur — reading them back
 * out of the table would mean a query per page load, and would silently drop an event
 * simply because nothing had happened yet.
 */
final class AuditEvent extends Enum
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';
    public const BATCH_UPLOADED = 'batch_uploaded';
    public const BATCH_REJECTED = 'batch_rejected';

    /**
     * Map an event to how it is described in the interface.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::BATCH_UPLOADED => 'Batch uploaded',
            self::BATCH_REJECTED => 'Batch rejected',
            default => ucfirst((string) $value),
        };
    }

    /**
     * Return every event as {value, name} option arrays for select inputs.
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
}
