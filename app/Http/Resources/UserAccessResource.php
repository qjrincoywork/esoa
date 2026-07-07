<?php

namespace App\Http\Resources;

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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value'    => $this->id,
            'name'     => trim(($this->username ?? '') . ' (' . ($this->email ?? '') . ')'),
            'accounts' => $this->userAccounts->map(fn ($account) => [
                'account_type' => $account->account_type,
                'account_code' => $account->account_code,
                'branch_code'  => $account->branch_code,
            ])->values(),
        ];
    }
}
