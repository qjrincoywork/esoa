<?php

namespace App\Enums;

enum Status: int
{
    case Active = 1;
    case InActive = 0;

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::InActive => 'In Active',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'bg-blue-500/20 text-blue-300 border-blue-500/30',
            self::InActive => 'bg-red-500/20 text-red-300 border-red-500/30',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::Active->value, 'name' => self::Active->label()],
            ['value' => self::InActive->value, 'name' => self::InActive->label()],
        ];
    }
}
