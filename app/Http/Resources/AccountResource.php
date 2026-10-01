<?php

namespace App\Http\Resources;

use App\Enums\AccountMappingBadge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The mapping badges are merged in only when the lookup attached what they are
     * decided from (`with_badges`, {@see \App\Helpers\SqlDatabase::getAccountsByParams()}),
     * so the pickers that never asked for them keep their plain {name, value} shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->ac_name . ' (' . $this->ac_code . ')',
            'value' => $this->ac_code,
            $this->mergeWhen(isset($this->resource->member_count), fn () => AccountMappingBadge::forRow(
                AccountMappingBadge::ACCOUNT,
                $this->account_expiry,
                $this->member_count
            )),
        ];
    }
}
