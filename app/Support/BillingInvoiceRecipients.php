<?php

namespace App\Support;

use App\Enums\EmailNotification;
use App\Models\Soa;
use App\Models\User;
use App\Models\UserAccount;

/**
 * Resolves who a billing invoice is announced to: every account-side (HR) user mapped
 * to the invoice's account/branch in `user_accounts` ({@see User::scopeAccountAdminsCovering()}).
 *
 * Two questions, from one lookup: whether anyone is mapped to the pair at all
 * ({@see coversAccount()}, what upload validation asks), and which of them are actually
 * emailed ({@see forAccount()}) — users excluded from the new-invoice notification
 * ({@see EmailNotification::NEW_BILLING_INVOICE}) still see the invoice in the system,
 * they are only left off the notice.
 *
 * Answers are memoised per account/branch pair for the lifetime of the instance, so a
 * batch upload that files many invoices under the same pair costs one lookup per pair
 * rather than one per invoice. Share one instance across a batch; a single upload can
 * simply let the mail helper create its own.
 */
final class BillingInvoiceRecipients
{
    /**
     * Mapped users already resolved, keyed by {@see UserAccount::mappingKey()}: each
     * distinct address and whether its owner is sent the new-invoice notification.
     *
     * @var array<string, array<string, bool>>
     */
    private array $resolved = [];

    /**
     * The distinct email addresses of the HR users who can see the given invoice and
     * are sent the new-invoice notification.
     *
     * @return list<string>
     */
    public function for(Soa $soa): array
    {
        return $this->forAccount($soa->account_code, $soa->branch_code);
    }

    /**
     * The same, by account/branch.
     *
     * @return list<string>
     */
    public function forAccount(?string $accountCode, ?string $branchCode): array
    {
        return array_keys(array_filter($this->mapped($accountCode, $branchCode)));
    }

    /**
     * Whether any HR user is mapped to the account/branch, whether or not they are
     * emailed — what upload validation asks of an invoice not saved yet
     * ({@see \App\Rules\HasBillingInvoiceRecipients}).
     */
    public function coversAccount(?string $accountCode, ?string $branchCode): bool
    {
        return $this->mapped($accountCode, $branchCode) !== [];
    }

    /**
     * Every distinct address mapped to the pair, flagged with whether it is emailed.
     *
     * @return array<string, bool>
     */
    private function mapped(?string $accountCode, ?string $branchCode): array
    {
        $accountCode = trim((string) $accountCode);
        $branchCode = trim((string) $branchCode) ?: null;

        if ($accountCode === '') {
            return [];
        }

        return $this->resolved[UserAccount::mappingKey($accountCode, $branchCode)] ??= User::query()
            ->accountAdminsCovering($accountCode, $branchCode)
            // withCount, not withExists: SQL Server cannot select an EXISTS predicate.
            ->withCount(['notificationExclusions as excluded_count' => fn ($exclusion) => $exclusion
                ->where('notification', EmailNotification::NEW_BILLING_INVOICE)])
            ->get(['id', 'email'])
            ->mapWithKeys(static fn (User $user): array => [trim((string) $user->email) => (int) $user->excluded_count === 0])
            // A blank address cannot be mailed and does not make the pair covered.
            ->except([''])
            ->all();
    }
}
