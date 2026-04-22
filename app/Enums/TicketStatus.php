<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class TicketStatus extends Enum
{
    public const OPEN = 1;
    public const IN_PROGRESS = 2;
    public const RESOLVED = 3;
    public const CLOSED = 4;

    public static function label($value): string
    {
        return match ($value) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
        };
    }

    public static function color($value): string
    {
        return match ($value) {
            self::OPEN => 'bg-blue-500/20 text-blue-500 border-blue-500/30',
            self::IN_PROGRESS => 'bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::RESOLVED => 'bg-green-500/20 text-green-500 border-green-500/30',
            self::CLOSED => 'bg-gray-500/20 text-gray-500 border-gray-500/30',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::OPEN, 'name' => self::label(self::OPEN)],
            ['value' => self::IN_PROGRESS, 'name' => self::label(self::IN_PROGRESS)],
            ['value' => self::RESOLVED, 'name' => self::label(self::RESOLVED)],
            ['value' => self::CLOSED, 'name' => self::label(self::CLOSED)],
        ];
    }
}
