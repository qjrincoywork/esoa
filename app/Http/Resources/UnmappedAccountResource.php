<?php

namespace App\Http\Resources;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One HMS account that nobody has been given access to yet.
 *
 * Everything a reader needs to decide whether the gap matters travels with the row:
 * how the account is classified, whether it is still a live account, and how many
 * cardholders are sitting behind it — an account with thousands of members and no user
 * is a different problem from a cancelled one with none.
 *
 * `member_count` and `mapped_users` are filled in by
 * {@see \App\Helpers\SqlDatabase::getUnassignedAccountsByParams()} for the page being
 * shown; neither is resolved here, because doing so per row would be one query per
 * account. `mapped_users` is an empty list unless the listing was asked to include what
 * is already mapped, since the default listing excludes it and the lookup would be wasted.
 */
class UnmappedAccountResource extends JsonResource
{
    /**
     * Transform the account into a listing row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = (string) $this->ac_code;
        $accountType = AccountType::fromAccountCode($accountCode);

        return [
            // The pair of codes identifies a mapping, so the row can be handed
            // straight to the mapping screen; an account row grants every branch.
            'account_code' => $accountCode,
            'branch_code' => null,

            // HMS stores a handful of names with stray whitespace and legacy encodings.
            'account_name' => CommonHelper::convertStringEncoding(trim((string) $this->ac_name)),
            'main_account_code' => $this->ac_ma_code,

            // Null where the code matches no prefix the enum knows; shown as-is
            // rather than forced into a group it does not belong to.
            'code_prefix' => AccountCodePrefix::of($accountCode),

            'account_type' => $accountType,
            'account_type_label' => AccountType::label($accountType),

            // Cancelled and expired accounts are listed too — they are just as
            // mappable — so the row says which it is rather than leaving the reader
            // to guess why a long-dead account has no user.
            'is_active' => AccountStatus::isActive($this->ac_status),

            'member_count' => (int) ($this->member_count ?? 0),

            // Who, if anyone, already has this account — populated only when the
            // listing was asked to include mapped rows; otherwise always empty.
            'mapped_users' => $this->mapped_users ?? [],
            'is_mapped' => !empty($this->mapped_users),
        ];
    }
}
