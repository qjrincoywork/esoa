<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The kind of device a request came from, as read off its user agent
 * ({@see \App\Support\UserAgent}).
 *
 * Automated clients are their own kind: a script or crawler in a user's trail is worth
 * noticing, not filing under "desktop".
 */
final class DeviceType extends Enum
{
    public const DESKTOP = 'desktop';
    public const MOBILE = 'mobile';
    public const TABLET = 'tablet';
    public const BOT = 'bot';
    public const UNKNOWN = 'unknown';

    /**
     * Map a device type to how it is named in the interface.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::DESKTOP => 'Desktop',
            self::MOBILE => 'Mobile',
            self::TABLET => 'Tablet',
            self::BOT => 'Automated client',
            default => 'Unknown device',
        };
    }
}
