<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->ch_id,
            'policynum'    => $this->ch_policynum,
            'firstname'    => $this->ch_firstname,
            'lastname'     => $this->ch_lastname,
            'middlename'   => $this->ch_middlename,
            'suffix'       => $this->ch_suffix,
            'account_code' => $this->ch_accountid,
            'company_name' => $this->ac_name,
            'claimnum'     => $this->claimnum,
            'batch_number' => $this->batch_number,
            'process_date' => CommonHelper::formatDate($this->process_date),
        ];
    }
}
