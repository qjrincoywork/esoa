<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The credential reports the users list can export.
 *
 * Each report is a fixed column set over the same filtered user query
 * ({@see \App\Exports\UserCredentialReportExporter}), so adding one means a value here
 * plus its columns there — no new route, request or button wiring.
 */
final class UserCredentialReport extends Enum
{
    /** Every user in the current filter with their full credential state. */
    public const CREDENTIALS = 'credentials';

    /** Users who were sent credentials, split into who has accessed them and who has not. */
    public const ACCESS = 'access';

    public static function label($value): string
    {
        return match ($value) {
            self::CREDENTIALS => 'User Credentials',
            self::ACCESS => 'Credential Access',
            default => '',
        };
    }

    public static function description($value): string
    {
        return match ($value) {
            self::CREDENTIALS => 'Every user matching the current filters, with credential and password status.',
            self::ACCESS => 'Users sent credentials: who has accessed them vs. who has not yet.',
            default => '',
        };
    }

    /**
     * Download filename stem, timestamped by the exporter.
     */
    public static function filePrefix($value): string
    {
        return match ($value) {
            self::ACCESS => 'user_credential_access',
            default => 'user_credentials',
        };
    }

    /**
     * Every report as {value, name, description} options for the export menu.
     *
     * @return array<array{value:string,name:string,description:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'name' => self::label($value),
                'description' => self::description($value),
            ],
            [self::CREDENTIALS, self::ACCESS]
        );
    }
}
