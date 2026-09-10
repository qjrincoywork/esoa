<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the branch into a picker option.
     *
     * `value`/`name` are what a combobox binds to. The owning account travels with it
     * as well, because a branch found by a directory-wide search has no other way to
     * say which account it belongs to — and a branch is only mappable as an
     * account/branch pair. The code is already on the row; the name comes from the
     * memo, so prime it with {@see CommonHelper::primeAccountNames()} for the whole
     * page first or each row resolves its own.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = $this->br_ac_code;

        return [
            // Some HMS branch names are stored with leading newlines, which would
            // otherwise blow out the row height wherever they are listed.
            'name' => trim((string) $this->br_branch_name),
            'value' => $this->br_code,
            'account_code' => $accountCode,
            // Falls back to the code: 20-odd branches reference an account HMS no
            // longer has, and they still need to read as something.
            'account_name' => CommonHelper::accountName($accountCode) ?: $accountCode,
        ];
    }
}
