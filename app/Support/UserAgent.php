<?php

namespace App\Support;

use App\Enums\DevicePlatform;
use App\Enums\DeviceType;

/**
 * What a user-agent string says about the device behind a request.
 *
 * The audit trail stores the raw agent ({@see AuditContext}); this reads it back as the
 * three things a person looks for — the operating system, the browser, and whether it
 * was a phone, a tablet or a computer. It is a best-effort reading, not a fingerprint:
 * agents can be spoofed, and modern browsers deliberately freeze parts of the string
 * (every Windows 10 and 11 machine reports "Windows NT 10.0"; every Mac reports
 * 10.15.7), so versions are only given where the string still carries a real one.
 *
 * Detection is table-driven: a new platform or browser is one pattern, in order.
 */
final class UserAgent
{
    /**
     * Operating systems, most specific first: iOS agents also say "Mac OS X", and
     * Android and ChromeOS agents also say "Linux".
     *
     * @var array<string, string>
     */
    private const PLATFORMS = [
        DevicePlatform::IOS => '/\b(?:iPhone|iPad|iPod)\b.*?\bOS (\d+(?:_\d+)*)/i',
        DevicePlatform::ANDROID => '/\bAndroid(?:\s+([\d.]+))?/i',
        DevicePlatform::CHROME_OS => '/\bCrOS\b/',
        DevicePlatform::WINDOWS => '/\bWindows NT ([\d.]+)/i',
        DevicePlatform::MACOS => '/\b(?:Macintosh|Mac OS X)\b/i',
        DevicePlatform::LINUX => '/\b(?:Linux|X11)\b/i',
    ];

    /**
     * Browsers, most specific first: Edge, Opera and Samsung Internet all claim to be
     * Chrome, and Chrome claims to be Safari.
     *
     * @var array<string, string>
     */
    private const BROWSERS = [
        'Edge' => '/\bEdg(?:e|A|iOS)?\/(\d+)/',
        'Opera' => '/\b(?:OPR|Opera)\/(\d+)/',
        'Samsung Internet' => '/\bSamsungBrowser\/(\d+)/',
        'Firefox' => '/\b(?:Firefox|FxiOS)\/(\d+)/',
        'Chrome' => '/\b(?:Chrome|CriOS)\/(\d+)/',
        'Safari' => '/\bVersion\/(\d+)(?:[\d.]*) .*Safari\//',
    ];

    /** Scripts, crawlers and HTTP libraries — anything not a person at a browser. */
    private const BOT_PATTERN = '/bot\b|crawl|spider|slurp|curl\/|wget\/|python-requests|guzzlehttp|postmanruntime|okhttp/i';

    /**
     * Windows reports its kernel version; people know it by its marketing name.
     *
     * @var array<string, string>
     */
    private const WINDOWS_VERSIONS = [
        '10.0' => '10/11',
        '6.3' => '8.1',
        '6.2' => '8',
        '6.1' => '7',
    ];

    private function __construct(
        public readonly string $platform,
        public readonly ?string $platformVersion,
        public readonly string $deviceType,
        public readonly ?string $browser,
        public readonly ?string $browserVersion,
    ) {
    }

    /**
     * Read a user-agent string; null when there is none to read.
     */
    public static function parse(?string $userAgent): ?self
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        [$platform, $platformVersion] = self::detectPlatform($userAgent);
        [$browser, $browserVersion] = self::detectBrowser($userAgent);

        return new self(
            $platform,
            $platformVersion,
            self::detectDeviceType($userAgent, $platform),
            $browser,
            $browserVersion,
        );
    }

    /**
     * The reading as the detail pane renders it.
     *
     * @return array{platform: string, platform_label: string, platform_version: string|null, vendor: string|null, device_type: string, device_label: string, browser: string|null, browser_version: string|null}
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'platform_label' => DevicePlatform::label($this->platform),
            'platform_version' => $this->platformVersion,
            'vendor' => DevicePlatform::vendor($this->platform),
            'device_type' => $this->deviceType,
            'device_label' => DeviceType::label($this->deviceType),
            'browser' => $this->browser,
            'browser_version' => $this->browserVersion,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function detectPlatform(string $userAgent): array
    {
        foreach (self::PLATFORMS as $platform => $pattern) {
            if (!preg_match($pattern, $userAgent, $matches)) {
                continue;
            }

            $version = $matches[1] ?? null;

            return [$platform, match ($platform) {
                DevicePlatform::IOS => str_replace('_', '.', (string) $version),
                DevicePlatform::WINDOWS => self::WINDOWS_VERSIONS[$version] ?? null,
                // Chrome's reduced agent freezes every Android at "10; K" — the "K" in
                // place of a model is the tell that the version is not the real one.
                DevicePlatform::ANDROID => $version && !preg_match('/Android [\d.]+; K\)/', $userAgent) ? $version : null,
                default => null,
            }];
        }

        return [DevicePlatform::UNKNOWN, null];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function detectBrowser(string $userAgent): array
    {
        foreach (self::BROWSERS as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return [$browser, $matches[1] ?? null];
            }
        }

        return [null, null];
    }

    private static function detectDeviceType(string $userAgent, string $platform): string
    {
        return match (true) {
            (bool) preg_match(self::BOT_PATTERN, $userAgent) => DeviceType::BOT,
            // Android tablets are the Android agents without "Mobile".
            (bool) preg_match('/\b(?:iPad|Tablet)\b/i', $userAgent),
            $platform === DevicePlatform::ANDROID && !str_contains($userAgent, 'Mobile') => DeviceType::TABLET,
            (bool) preg_match('/\b(?:iPhone|iPod|Mobile|Android)\b/i', $userAgent) => DeviceType::MOBILE,
            in_array($platform, [DevicePlatform::WINDOWS, DevicePlatform::MACOS, DevicePlatform::LINUX, DevicePlatform::CHROME_OS], true) => DeviceType::DESKTOP,
            default => DeviceType::UNKNOWN,
        };
    }
}
