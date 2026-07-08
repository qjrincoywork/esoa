<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class Gender extends Enum
{
    public const PREFER_NOT_TO_SAY = 1;
    public const MALE = 2;
    public const FEMALE = 3;

    /**
     * Map a gender value to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::PREFER_NOT_TO_SAY => 'Prefer Not to Say',
            self::MALE => 'Male',
            self::FEMALE => 'Female',
        };
    }

    /**
     * Return all gender options as {value, name} arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::PREFER_NOT_TO_SAY, 'name' => self::label(self::PREFER_NOT_TO_SAY)],
            ['value' => self::MALE, 'name' => self::label(self::MALE)],
            ['value' => self::FEMALE, 'name' => self::label(self::FEMALE)],
        ];
    }
}
