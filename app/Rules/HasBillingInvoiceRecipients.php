<?php

namespace App\Rules;

use App\Support\BillingInvoiceRecipients;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Pass only when the account (and branch, when given) has at least one HR user mapped
 * to it who can see the billing invoice.
 *
 * Applied to `account_code`; the branch is read from the same payload. The mapped users
 * are resolved exactly as the notice resolves them ({@see BillingInvoiceRecipients}).
 * A user excluded from the new-invoice notification still counts: they see the invoice in the
 * system and are only left off its notice. Hand in a shared resolver to validate many
 * rows with one lookup per account/branch pair.
 */
class HasBillingInvoiceRecipients implements DataAwareRule, ValidationRule
{
    /**
     * The payload under validation, for the branch code.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    public function __construct(protected ?BillingInvoiceRecipients $recipients = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * The same rule answering from the given resolver, so the caller can reuse its
     * lookups when sending the notices afterwards.
     */
    public function using(BillingInvoiceRecipients $recipients): static
    {
        return new static($recipients);
    }

    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $accountCode = trim((string) $value);

        // Blank is the "required" rule's to report.
        if ($accountCode === '') {
            return;
        }

        $branchCode = trim((string) ($this->data['branch_code'] ?? ''));
        $this->recipients ??= new BillingInvoiceRecipients();

        if ($this->recipients->coversAccount($accountCode, $branchCode)) {
            return;
        }

        $target = $branchCode !== ''
            ? "account {$accountCode}, branch {$branchCode}"
            : "account {$accountCode}";

        $fail("No HR user is mapped to {$target} to receive this billing invoice. Map a Group Account Admin or Account/Branch Admin to it before uploading.");
    }
}
