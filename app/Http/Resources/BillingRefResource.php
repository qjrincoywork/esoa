<?php

namespace App\Http\Resources;

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
            'name' => $this->bl_refid . ' - ' . number_format($this->bl_balance, 2),
            'value' => $this->bl_refid,
            'balance' => number_format($this->bl_balance, 2),
            'balance_raw' => (float) $this->bl_balance,
        ];
    }
}
