<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class RemittanceAdviceStatus extends Enum
{
    public const SUBMITTED         = 1; // Client created
    public const UNDER_REVIEW      = 2; // Billing validating
    public const VERIFIED          = 3; // Billing confirmed payment
    public const PARTIALLY_APPLIED = 4; // Billing applied to some SOAs
    public const FULLY_APPLIED     = 5; // Billing fully applied
    public const REJECTED          = 6; // Billing rejected
    public const CANCELLED         = 7; // Billing/Admin cancelled

    /**
     * Valid transitions: from => [allowed to] values.
     * Billing dept is the only actor for all transitions except SUBMITTED (set by client on create).
     */
    private const TRANSITIONS = [
        self::SUBMITTED         => [self::UNDER_REVIEW, self::CANCELLED],
        self::UNDER_REVIEW      => [self::VERIFIED, self::REJECTED, self::CANCELLED],
        self::VERIFIED          => [self::PARTIALLY_APPLIED, self::FULLY_APPLIED, self::CANCELLED],
        self::PARTIALLY_APPLIED => [self::FULLY_APPLIED, self::CANCELLED],
        self::FULLY_APPLIED     => [],
        self::REJECTED          => [self::CANCELLED],
        self::CANCELLED         => [],
    ];

    public static function label(int $value): string
    {
        return match ($value) {
            self::SUBMITTED         => 'Submitted',
            self::UNDER_REVIEW      => 'Under Review',
            self::VERIFIED          => 'Verified',
            self::PARTIALLY_APPLIED => 'Partially Applied',
            self::FULLY_APPLIED     => 'Fully Applied',
            self::REJECTED          => 'Rejected',
            self::CANCELLED         => 'Cancelled',
            default                 => 'Unknown',
        };
    }

    public static function color(int $value): string
    {
        return match ($value) {
            self::SUBMITTED         => 'p-3 rounded-lg bg-gray-500/20 text-gray-500 border-gray-500/30',
            self::UNDER_REVIEW      => 'p-3 rounded-lg bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::VERIFIED          => 'p-3 rounded-lg bg-blue-500/20 text-blue-500 border-blue-500/30',
            self::PARTIALLY_APPLIED => 'p-3 rounded-lg bg-orange-500/20 text-orange-500 border-orange-500/30',
            self::FULLY_APPLIED     => 'p-3 rounded-lg bg-green-500/20 text-green-500 border-green-500/30',
            self::REJECTED          => 'p-3 rounded-lg bg-red-500/20 text-red-500 border-red-500/30',
            self::CANCELLED         => 'p-3 rounded-lg bg-slate-500/20 text-slate-500 border-slate-500/30',
            default                 => 'p-3 rounded-lg bg-gray-500/20 text-gray-500 border-gray-500/30',
        };
    }

    public static function list(): array
    {
        return array_map(
            fn ($value) => ['value' => $value, 'name' => self::label($value), 'color' => self::color($value)],
            self::getValues()
        );
    }

    /**
     * Check whether transitioning from $current to $next is allowed.
     */
    public static function canTransition(int $current, int $next): bool
    {
        return in_array($next, self::TRANSITIONS[$current] ?? [], true);
    }

    /**
     * Return the allowed next statuses for a given current status.
     *
     * @return int[]
     */
    public static function allowedNext(int $current): array
    {
        return self::TRANSITIONS[$current] ?? [];
    }
}
