<?php

namespace App\Support;

use App\Models\Soa;
use App\Models\User;
use App\Models\UserAccount;

/**
 * Resolves who a billing invoice is announced to: every account-side (HR) user mapped
 * to the invoice's account/branch in `user_accounts` ({@see User::scopeAccountAdminsCovering()}).
 *
 * Answers are memoised per account/branch pair for the lifetime of the instance, so a
 * batch upload that files many invoices under the same pair costs one lookup per pair
 * rather than one per invoice. Share one instance across a batch; a single upload can
 * simply let the mail helper create its own.
 */
final class BillingInvoiceRecipients
{
    /**
     * Emails already resolved, keyed by {@see UserAccount::mappingKey()}.
     *
     * @var array<string, list<string>>
     */
    private array $resolved = [];

    /**
     * The distinct email addresses of the HR users who can see the given invoice.
     *
     * @return list<string>
     */
    public function for(Soa $soa): array
    {
        return $this->forAccount($soa->account_code, $soa->branch_code);
    }

    /**
     * The same, for an invoice not saved yet — what upload validation asks
     * ({@see \App\Rules\HasBillingInvoiceRecipients}).
     *
     * @return list<string>
     */
    public function forAccount(?string $accountCode, ?string $branchCode): array
    {
        $accountCode = trim((string) $accountCode);
        $branchCode = trim((string) $branchCode) ?: null;

        if ($accountCode === '') {
            return [];
        }

        return $this->resolved[UserAccount::mappingKey($accountCode, $branchCode)] ??= User::query()
            ->accountAdminsCovering($accountCode, $branchCode)
            ->pluck('email')
            ->map(static fn ($email): string => trim((string) $email))
            ->filter()
            ->unique(static fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }
}
