<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Where an advisory stands right now, derived from its switch and its window
 * ({@see \App\Models\Advisory::state()}). Never stored: the same row moves from
 * Scheduled to Live to Expired on its own as the clock passes its dates.
 */
final class AdvisoryState extends Enum
{
    public const LIVE = 1;
    public const SCHEDULED = 2;
    public const EXPIRED = 3;
    public const DISABLED = 4;

    /**
     * Map a state to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::LIVE => 'Live',
            self::SCHEDULED => 'Scheduled',
            self::EXPIRED => 'Expired',
            self::DISABLED => 'Disabled',
        };
    }

    /**
     * Return the Tailwind CSS badge classes (background/text/border) for the given state.
     *
     * @param int $value
     * @return string
     */
    public static function color($value): string
    {
        return match ($value) {
            self::LIVE => 'bg-green-500/20 text-green-600 border-green-500/30',
            self::SCHEDULED => 'bg-blue-500/20 text-blue-500 border-blue-500/30',
            self::EXPIRED => 'bg-gray-500/20 text-gray-500 border-gray-500/30',
            self::DISABLED => 'bg-red-500/20 text-red-500 border-red-500/30',
        };
    }

    /**
     * Return every state as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (int $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues()
        );
    }
}
