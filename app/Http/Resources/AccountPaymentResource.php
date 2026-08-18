<?php

namespace App\Http\Resources;

use App\Enums\AccountPaymentMode;
use App\Helpers\CommonHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentResource extends JsonResource
{
    /**
     * Transform the account payment into an array, mapping the mode of payment to its
     * label, joining related SOA numbers, formatting dates, and issuing short-lived
     * preview tokens for any attached PDF file when a user is authenticated.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_invoice' => $this->soas->isNotEmpty() ? implode(', ', $this->soas->pluck('soa_number')->toArray()) : null,
            'soa_ids' => $this->soas->pluck('id')->toArray(),
            'soas' => $this->soas->map(fn ($soa) => [
                'id' => $soa->id,
                'soa_number' => $soa->soa_number,
                'account_code' => $soa->account_code,
                'branch_code' => $soa->branch_code,
            ])->values()->toArray(),
            'deposit_date' => CommonHelper::formatDate($this->deposit_date),
            /** ISO (Y-m-d) form of deposit_date, for populating an <input type="date"> on the edit form. */
            'deposit_date_value' => $this->deposit_date ? Carbon::parse($this->deposit_date)->format('Y-m-d') : null,
            'mode_of_payment' => AccountPaymentMode::label((int) $this->mode_of_payment),
            'mode_of_payment_value' => $this->mode_of_payment,
            'pdf' => $this->pdf,
            'remarks' => $this->remarks,
            'created_by' => $this->resource->user->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at),
            'pdf_preview_token' => $this->pdf && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.account_payments'),
                    $this->pdf,
                    (int) $request->user()->id
                )
                : null,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
