<?php

namespace App\Http\Resources;

use App\Enums\AccountPaymentMode;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deposit_date' => CommonHelper::formatDate($this->deposit_date),
            'mode_of_payment' => AccountPaymentMode::label($this->mode_of_payment),
            'mode_of_payment_value' => $this->mode_of_payment,
            'remittance_advice' => $this->remittance_advice,
            'remarks' => $this->remarks,
            'created_by' => $this->resource->user->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at),
            'remittance_advice_preview_token' => $this->remittance_advice && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('ACCOUNT_PAYMENTS_DISK', 'public'),
                    $this->remittance_advice,
                    (int) $request->user()->id
                )
                : null,
        ];
    }
}
