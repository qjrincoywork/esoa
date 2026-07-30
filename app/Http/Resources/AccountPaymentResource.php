<?php

namespace App\Http\Resources;

use App\Enums\AccountPaymentMode;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentResource extends JsonResource
{
    /**
     * Transform the account payment into an array, mapping the mode of payment to its
     * label, joining related SOA numbers, formatting dates, and issuing short-lived
     * preview tokens for any attached image/PDF/Excel files when a user is authenticated.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_invoice' => $this->soas->isNotEmpty() ? implode(', ', $this->soas->pluck('soa_number')->toArray()) : null,
            'deposit_date' => CommonHelper::formatDate($this->deposit_date),
            'mode_of_payment' => AccountPaymentMode::label((int) $this->mode_of_payment),
            'mode_of_payment_value' => $this->mode_of_payment,
            'image' => $this->image,
            'pdf' => $this->pdf,
            'excel' => $this->excel,
            'remarks' => $this->remarks,
            'created_by' => $this->resource->user->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at),
            'image_preview_token' => $this->image && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.account_payments'),
                    $this->image,
                    (int) $request->user()->id
                )
                : null,
            'pdf_preview_token' => $this->pdf && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.account_payments'),
                    $this->pdf,
                    (int) $request->user()->id
                )
                : null,
            'excel_preview_token' => $this->excel && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.account_payments'),
                    $this->excel,
                    (int) $request->user()->id
                )
                : null,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
