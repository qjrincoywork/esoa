<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

use function Psy\debug;

/**
 * Enum representation of bill reference from values.
 *
 * @extends Enum<int>
 */
final class BillRefFrom extends Enum
{
    public const CLAIMS = 1;
    public const MDA = 2;

    /**
     * Get the human readable label for a given bill reference from value.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::CLAIMS => 'Claims',
            self::MDA => 'MDA',
        };
    }

    /**
     * Get all bill reference from as an array of value/label pairs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::CLAIMS, 'name' => self::label(self::CLAIMS)],
            ['value' => self::MDA, 'name' => self::label(self::MDA)],
        ];
    }
}
