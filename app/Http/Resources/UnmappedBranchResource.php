<?php

namespace App\Http\Resources;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountStanding;
use App\Enums\AccountType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One HMS branch that nobody has been given access to yet.
 *
 * The branch counterpart of {@see UnmappedAccountResource}, with the owning account
 * along for the ride: a branch is only ever mapped as an account/branch pair, and a
 * branch name on its own ("Main", "Cebu") says nothing about whose branch it is.
 *
 * The account name comes from the memo {@see CommonHelper::primeAccountNames()} fills,
 * so prime the whole page before serialising — resolving it per row would be a lookup
 * per branch. It is deliberately not joined: `ac_code` is not unique in HMS and some
 * branches point at an account that no longer exists, so a join would duplicate and
 * drop rows and corrupt the paginator's counts.
 */
class UnmappedBranchResource extends JsonResource
{
    /**
     * Transform the branch into a listing row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = (string) $this->br_ac_code;
        $accountType = AccountType::fromAccountCode($accountCode);

        return [
            'account_code' => $accountCode,
            'branch_code' => (string) $this->br_code,

            // Falls back to the code: branches whose account HMS no longer has still
            // need to read as something.
            'account_name' => CommonHelper::accountName($accountCode) ?: $accountCode,

            // Some HMS branch names are stored with leading newlines, which would
            // otherwise blow out the row height wherever they are listed.
            'branch_name' => CommonHelper::convertStringEncoding(trim((string) $this->br_branch_name)),

            // A branch is classified by the account it belongs to, not by its own code.
            'code_prefix' => AccountCodePrefix::of($accountCode),
            'account_type' => $accountType,
            'account_type_label' => AccountType::label($accountType),

            // A branch has no standing of its own: these are its account's, attached
            // per page by SqlDatabase::attachBranchAccountStanding().
            'is_active' => (bool) ($this->account_is_active ?? false),
            'standing' => AccountStanding::present(
                AccountStanding::resolveFrom((bool) ($this->account_is_active ?? false), $this->account_expiry ?? null)
            ),
            'expiry_date' => CommonHelper::formatDate($this->account_expiry ?? null),

            'member_count' => (int) ($this->member_count ?? 0),

            // Who, if anyone, already has this branch — populated only when the
            // listing was asked to include mapped rows; otherwise always empty.
            'mapped_users' => $this->mapped_users ?? [],
            'is_mapped' => !empty($this->mapped_users),
        ];
    }
}
