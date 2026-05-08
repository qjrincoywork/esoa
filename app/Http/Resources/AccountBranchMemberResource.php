<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountBranchMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'claimnum' => $this->bl_claimnum,
            'policynum' => $this->ch_policynum,
            'id' => $this->ch_id,
            'firstname' => $this->ch_firstname,
            'lastname' => $this->ch_lastname,
            'middlename' => $this->ch_middlename,
            'suffix' => $this->ch_suffix,
        ];
    }
}
