<?php

namespace App\Http\Resources;

use App\Enums\AccountType;
use App\Helpers\CommonHelper;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One account/branch mapping as the mapping screen shows it.
 *
 * Rows store codes only, so each is labelled with the account and branch names the
 * pickers display, letting a saved mapping and a freshly dragged one read alike.
 * Prime the name memo with {@see CommonHelper::primeAccountBranchNames()} before
 * serialising a collection — otherwise every row resolves its own codes against HMS.
 */
class UserAccountMappingResource extends JsonResource
{
    /**
     * Transform the mapping into a row for the drag-and-drop mapping panel.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        [$row] = CommonHelper::withAccountBranchNames([$this->resource]);

        $accountType = $row['account_type'] ?? null;
        $accountCode = $row['account_code'] ?? null;
        $branchCode = $row['branch_code'] ?? null;

        return [
            'id' => $row['id'] ?? null,
            // Client-side identity: unsaved rows have no id, so both ends dedupe on this.
            'key' => UserAccount::mappingKey($accountCode, $branchCode),
            'account_type' => $accountType,
            // An unrecognised stored code is shown as-is rather than blowing up the pane.
            'account_type_label' => AccountType::hasValue($accountType)
                ? AccountType::label($accountType)
                : $accountType,
            'account_code' => $accountCode,
            'account_name' => $row['account_name'] ?? $accountCode,
            'branch_code' => $branchCode,
            'branch_name' => $row['branch_name'] ?? $branchCode,
        ];
    }
}
