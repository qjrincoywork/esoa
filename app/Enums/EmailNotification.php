<?php

namespace App\Enums;

use App\Mail\{
    AccountPaymentNotification,
    BillingInvoiceDueReminder,
    BillingInvoiceStatusChanged,
    ConcernNotification,
    NewBillingInvoiceUploaded
};
use BenSampo\Enum\Enum;

/**
 * The system's email notifications a user can be excluded from, one by one.
 *
 * Every user receives them all until excluded; an exclusion is a row in
 * `user_notification_exclusions` ({@see \App\Models\UserNotificationExclusion}). An
 * excluded user is left off that notification while it still reaches its other
 * recipients. Account mail is deliberately not listed — login credentials and password
 * resets always go out, or the user could never sign in — so a mailable with no type
 * here ({@see forMailable()}) is never held back.
 */
final class EmailNotification extends Enum
{
    /** A billing invoice was created or uploaded for an account/branch the user is mapped to. */
    public const NEW_BILLING_INVOICE = 'new_billing_invoice';

    /** The status of a billing invoice the user acted on changed. */
    public const BILLING_INVOICE_STATUS_CHANGED = 'billing_invoice_status_changed';

    /** The user's billing invoices are coming due or past due. */
    public const BILLING_INVOICE_DUE_REMINDER = 'billing_invoice_due_reminder';

    /** The copy of a concern or remittance advice the user filed. */
    public const CONCERN_REMITTANCE_COPY = 'concern_remittance_copy';

    /**
     * Map a notification to its human-readable label.
     */
    public static function label($value): string
    {
        return match ($value) {
            self::NEW_BILLING_INVOICE => 'New Billing Invoice',
            self::BILLING_INVOICE_STATUS_CHANGED => 'Billing Invoice Status Change',
            self::BILLING_INVOICE_DUE_REMINDER => 'Billing Invoice Due Reminder',
            self::CONCERN_REMITTANCE_COPY => 'Concern & Remittance Advice Copy',
            default => '',
        };
    }

    /**
     * What the notification is, in a sentence, for the settings screen.
     */
    public static function description($value): string
    {
        return match ($value) {
            self::NEW_BILLING_INVOICE => 'When a billing invoice is uploaded for an account or branch the user is mapped to.',
            self::BILLING_INVOICE_STATUS_CHANGED => "When the status of a billing invoice the user acted on changes.",
            self::BILLING_INVOICE_DUE_REMINDER => "Reminders about the user's billing invoices that are coming due or past due.",
            self::CONCERN_REMITTANCE_COPY => 'A copy of each concern or remittance advice the user files.',
            default => '',
        };
    }

    /**
     * Every notification as {value, name, description} options, for the list filter and
     * the settings screen.
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
            self::getValues()
        );
    }

    /**
     * The notification a mailable is sent as, or null for mail that is not a
     * notification and therefore always goes out (e.g. login credentials).
     *
     * @param  class-string  $mailClass
     */
    public static function forMailable(string $mailClass): ?string
    {
        return match ($mailClass) {
            NewBillingInvoiceUploaded::class => self::NEW_BILLING_INVOICE,
            BillingInvoiceStatusChanged::class => self::BILLING_INVOICE_STATUS_CHANGED,
            BillingInvoiceDueReminder::class => self::BILLING_INVOICE_DUE_REMINDER,
            ConcernNotification::class, AccountPaymentNotification::class => self::CONCERN_REMITTANCE_COPY,
            default => null,
        };
    }
}
