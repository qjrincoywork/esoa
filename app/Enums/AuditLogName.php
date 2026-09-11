<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * The audit channels written to `activity_log`.
 *
 * Spatie keys every entry by a log name, and it is the column the trail is filtered
 * and reported on — so the names live here rather than as strings scattered across
 * models, keeping "which modules are audited" answerable in one place.
 *
 * The value is what lands in the database; the label is how the module is spoken about
 * in the interface, which is not always the model's name (a Soa is a billing invoice,
 * an AccountPayment is a remittance advice).
 */
final class AuditLogName extends Enum
{
    public const BILLING_INVOICE = 'billing_invoice';
    public const CONCERN = 'concern';
    public const REMITTANCE_ADVICE = 'remittance_advice';

    /**
     * Map an audit channel to the module name people know it by.
     *
     * @param  string  $value
     */
    public static function label($value): string
    {
        return match ($value) {
            self::BILLING_INVOICE => 'Billing invoice',
            self::CONCERN => 'Concern',
            self::REMITTANCE_ADVICE => 'Remittance advice',
            default => 'Record',
        };
    }

    /**
     * Return every audit channel as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:string,name:string}>
     */
    public static function list(): array
    {
        return array_map(
            static fn (string $value): array => ['value' => $value, 'name' => self::label($value)],
            self::getValues()
        );
    }
}
