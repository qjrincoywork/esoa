<?php

namespace App\Http\Resources;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountStanding;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One HMS account, opened up from the unmapped listing.
 *
 * The listing shows what is needed to spot a gap; this shows what is needed to decide
 * what to do about it — how long the account runs, who to speak to, and why it was
 * cancelled if it was. `member_count` and `branch_count` are attached by the controller
 * rather than read from the row, because neither lives on `Accounts`.
 */
class AccountDirectoryDetailResource extends JsonResource
{
    /**
     * Transform the account into the detail pane's payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = (string) $this->ac_code;
        $accountType = AccountType::fromAccountCode($accountCode);

        return [
            'account_code' => $accountCode,
            'account_name' => CommonHelper::convertStringEncoding(trim((string) $this->ac_name)),
            'main_account_code' => $this->ac_ma_code,

            'code_prefix' => AccountCodePrefix::of($accountCode),
            'account_type' => $accountType,
            'account_type_label' => AccountType::label($accountType),
            'is_active' => AccountStatus::isActive($this->ac_status),
            'standing' => AccountStanding::present(AccountStanding::resolve($this->ac_status, $this->ac_expiry)),

            // HMS's own account-class letter. Shown as stored: it is a different thing
            // from the TPA/HMO split above, which this application derives from the code.
            'hms_account_type' => $this->ac_accttype,

            'address' => CommonHelper::convertStringEncoding(trim((string) $this->ac_address)) ?: null,
            'tin' => $this->ac_tin,
            'contact_person' => CommonHelper::convertStringEncoding(trim((string) $this->ac_conper)) ?: null,
            'contact_number' => $this->ac_phone,
            'agent_code' => $this->ac_agcode,

            'effectivity_date' => CommonHelper::formatDate($this->ac_effdate),
            'renewal_date' => CommonHelper::formatDate($this->ac_rendate),
            'expiry_date' => CommonHelper::formatDate($this->ac_expiry),
            'cancel_date' => CommonHelper::formatDate($this->ac_candate),
            'cancel_reason' => CommonHelper::convertStringEncoding(trim((string) $this->ac_cancel_reason)) ?: null,

            // Attached by the controller; see the class docblock.
            'member_count' => (int) ($this->member_count ?? 0),
            'branch_count' => (int) ($this->branch_count ?? 0),
        ];
    }
}
