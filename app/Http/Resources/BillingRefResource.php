<?php

namespace App\Http\Resources;

use App\Enums\BillRefFrom;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingRefResource extends JsonResource
{
    /**
     * Transform the billing reference into a combobox option, building a composite display
     * name (ref id, optional claim number, peso amount and posting date) and conditionally
     * including the balance when an amount is present.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $this->ref_id . ((int) request()->billing_ref_from == BillRefFrom::CLAIMS ? ' - ' . $this->cl_claimnum : '')
                . ' - '
                . config('vc.peso_sign') . number_format($this->amount, 2)
                . ' - '
                . CommonHelper::formatDate($this->date_posted);

        $balance = [];
        if ($this->amount) {
            $balance = [
                'balance' => number_format($this->amount, 2),
                'balance_raw' => (float) $this->amount,
            ];
        }

        return [
            'name' => $name,
            'value' => $this->ref_id,
            ...$balance,
        ];
    }
}
