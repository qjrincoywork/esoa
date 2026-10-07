<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The operating system a request came from, as read off its user agent
 * ({@see \App\Support\UserAgent}).
 *
 * Kept to the families a reader of the audit trail actually tells apart — "was this on
 * a phone or at a desk, Apple or Windows" — rather than every distribution or fork.
 */
final class DevicePlatform extends Enum
{
    public const WINDOWS = 'windows';
    public const MACOS = 'macos';
    public const IOS = 'ios';
    public const ANDROID = 'android';
    public const CHROME_OS = 'chrome_os';
    public const LINUX = 'linux';
    public const UNKNOWN = 'unknown';

    /**
     * Map a platform to how it is named in the interface.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::WINDOWS => 'Windows',
            self::MACOS => 'macOS',
            self::IOS => 'iOS',
            self::ANDROID => 'Android',
            self::CHROME_OS => 'ChromeOS',
            self::LINUX => 'Linux',
            default => 'Unknown OS',
        };
    }

    /**
     * Who makes the platform — what people mean by "an Apple device" or "a Windows PC".
     *
     * @param  string  $value
     */
    public static function vendor($value): ?string
    {
        return match ($value) {
            self::WINDOWS => 'Microsoft',
            self::MACOS, self::IOS => 'Apple',
            self::ANDROID, self::CHROME_OS => 'Google',
            default => null,
        };
    }
}
