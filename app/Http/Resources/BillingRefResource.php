<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingRefResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->bl_refid
                . ' - '
                . config('vc.peso_sign') . number_format($this->bl_balance, 2)
                . ' - '
                . CommonHelper::formatDate($this->bl_dateposted),
            'value' => $this->bl_refid,
            'balance' => number_format($this->bl_balance, 2),
            'balance_raw' => (float) $this->bl_balance,
        ];
    }
}
