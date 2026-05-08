<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Enum representation of boolean values as integers.
 *
 * @extends Enum<int>
 */
final class BooleanAsInteger extends Enum
{
    /**
     * The enum value for true.
     *
     * @var int
     */
    public const TRUE = 1;

    /**
     * The enum value for false.
     *
     * @var int
     */
    public const FALSE = 0;
}