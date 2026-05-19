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
            'name' => $this->ref_id
                . ' - '
                . config('vc.peso_sign') . number_format($this->amount, 2)
                . ' - '
                . CommonHelper::formatDate($this->date_posted),
            'value' => $this->ref_id,
            'balance' => number_format($this->amount, 2),
            'balance_raw' => (float) $this->amount,
        ];
    }
}
