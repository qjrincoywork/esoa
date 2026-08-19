<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight user option used by the "copy account access" picker.
 * Shaped as a SearchableCombobox item ({ value, name }) with the user's
 * account/branch access embedded so it can be copied without a second request.
 */
class UserAccessResource extends JsonResource
{
    /**
     * Transform the user into a combobox option, composing a "username (email)" display
     * name and embedding the user's account/branch access — codes plus their display
     * names, so copied rows read the same as freshly picked ones — allowing the copy to
     * happen without a second request.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value'    => $this->id,
            'name'     => trim(($this->username ?? '') . ' (' . ($this->email ?? '') . ')'),
            'accounts' => collect(CommonHelper::withAccountBranchNames($this->userAccounts))
                ->map(fn (array $account) => [
                    'account_type' => $account['account_type'],
                    'account_code' => $account['account_code'],
                    'account_name' => $account['account_name'],
                    'branch_code'  => $account['branch_code'],
                    'branch_name'  => $account['branch_name'],
                ])->values(),
        ];
    }
}
