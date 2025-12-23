<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class UntagType extends Enum
{
    public const USER_ERROR = 1;
    public const CLIENT_ERROR = 2;
    public const BOUNCED_RETURNED_CHECK = 3;
    public const OTHERS = 4;

    public static function label($value): string
    {
        return match ($value) {
            self::USER_ERROR => 'User Error',
            self::CLIENT_ERROR => 'Client Error',
            self::BOUNCED_RETURNED_CHECK => 'Bounced Returned Check',
            self::OTHERS => 'Other Reason',
        };
    }

    public static function list(): array
    {
        return [
            ['value' => self::USER_ERROR, 'name' => self::label(self::USER_ERROR)],
            ['value' => self::CLIENT_ERROR, 'name' => self::label(self::CLIENT_ERROR)],
            ['value' => self::BOUNCED_RETURNED_CHECK, 'name' => self::label(self::BOUNCED_RETURNED_CHECK)],
            ['value' => self::OTHERS, 'name' => self::label(self::OTHERS)],
        ];
    }
}
