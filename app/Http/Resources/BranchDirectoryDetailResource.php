<?php

namespace App\Http\Resources;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One HMS branch, opened up from the unmapped listing.
 *
 * The branch counterpart of {@see AccountDirectoryDetailResource}. A branch is only
 * ever mapped as an account/branch pair and its name says nothing about whose branch it
 * is, so the owning account travels with it — including whether that account is still
 * in force, which is the only sense in which a branch has a status at all.
 *
 * `account_name`, `account_is_active` and `member_count` are attached by the controller:
 * the first two come from the account, which is looked up separately rather than joined
 * ({@see \App\Helpers\SqlDatabase::getBranchDirectoryDetail()}), and the third from
 * `cholders`.
 */
class BranchDirectoryDetailResource extends JsonResource
{
    /**
     * Transform the branch into the detail pane's payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = (string) $this->br_ac_code;
        $accountType = AccountType::fromAccountCode($accountCode);

        return [
            'branch_code' => (string) $this->br_code,
            // Some HMS branch names are stored with leading newlines, which would
            // otherwise blow out the row height wherever they are listed.
            'branch_name' => CommonHelper::convertStringEncoding(trim((string) $this->br_branch_name)),

            'account_code' => $accountCode,
            // Falls back to the code: a few branches reference an account HMS no
            // longer has, and they still need to read as something.
            'account_name' => $this->account_name ?: $accountCode,
            'account_is_active' => (bool) ($this->account_is_active ?? false),
            'main_account_code' => $this->br_ma_code,

            // A branch is classified by the account it belongs to, not by its own code.
            'code_prefix' => AccountCodePrefix::of($accountCode),
            'account_type' => $accountType,
            'account_type_label' => AccountType::label($accountType),

            'address' => CommonHelper::convertStringEncoding(trim((string) $this->br_address)) ?: null,
            'tin' => $this->br_tin,
            'attention' => CommonHelper::convertStringEncoding(trim((string) $this->br_attention)) ?: null,
            'position' => CommonHelper::convertStringEncoding(trim((string) $this->br_position)) ?: null,

            'member_count' => (int) ($this->member_count ?? 0),
        ];
    }
}
