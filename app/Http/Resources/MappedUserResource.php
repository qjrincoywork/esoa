<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One user mapped to the account or branch behind the "Mapped Users" tab.
 *
 * Wraps a `UserAccount` row with its `user` relation eager-loaded
 * ({@see \App\Models\UserAccount::queryForAccountCode()} /
 * {@see \App\Models\UserAccount::queryForBranchCode()}); the pair of codes travels with
 * the row so a mapping that grants a whole account reads differently from one naming a
 * single branch of it.
 */
class MappedUserResource extends JsonResource
{
    /**
     * Transform the mapping into a listing row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'username' => $this->user?->username,
            'email' => $this->user?->email,
            'is_active' => (bool) ($this->user?->is_active ?? false),

            'account_type' => $this->account_type,
            'account_code' => $this->account_code,
            'branch_code' => $this->branch_code,

            // A blank branch code means the mapping grants every branch of the
            // account, not just the one the reader is looking at.
            'mapped_in_full' => $this->branch_code === null || $this->branch_code === '',

            'mapped_at' => CommonHelper::formatDate($this->created_at),
        ];
    }
}
