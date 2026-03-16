<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Enum representation of order values.
 *
 * @extends Enum<string>
 */
final class OrderType extends Enum
{
    /**
     * The enum value for asc.
     *
     * @var string
     */
    public const ASC = 'asc';

    /**
     * The enum value for desc.
     *
     * @var string
     */
    public const DESC = 'desc';
}
