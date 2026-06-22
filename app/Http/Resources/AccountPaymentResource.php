<?php

namespace App\Http\Resources;

use App\Enums\AccountPaymentMode;
use App\Enums\RemittanceAdviceStatus;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = (int) $this->status;

        return [
            'id'                   => $this->id,
            'billing_invoice'      => $this->whenLoaded('soas', fn () =>
                $this->soas->isNotEmpty()
                    ? implode(', ', $this->soas->pluck('soa_number')->toArray())
                    : null
            ),
            'status'               => $status,
            'status_label'         => RemittanceAdviceStatus::label($status),
            'status_color'         => RemittanceAdviceStatus::color($status),
            'allowed_next_statuses'=> RemittanceAdviceStatus::allowedNext($status),
            'amount'               => $this->amount,
            'credit_balance'       => $this->remainingBalance(),
            'deposit_date'         => CommonHelper::formatDate($this->deposit_date),
            'mode_of_payment'      => AccountPaymentMode::label((int) $this->mode_of_payment),
            'mode_of_payment_value'=> $this->mode_of_payment,
            'image'                => $this->image,
            'pdf'                  => $this->pdf,
            'excel'                => $this->excel,
            'remarks'              => $this->remarks,
            'created_by'           => $this->resource->user->username ?? null,
            'created_at'           => CommonHelper::formatDate($this->created_at),
            'image_preview_token'  => $this->image && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('ACCOUNT_PAYMENTS_DISK', 'public'),
                    $this->image,
                    (int) $request->user()->id
                )
                : null,
            'pdf_preview_token'    => $this->pdf && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('ACCOUNT_PAYMENTS_DISK', 'public'),
                    $this->pdf,
                    (int) $request->user()->id
                )
                : null,
            'excel_preview_token'  => $this->excel && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('ACCOUNT_PAYMENTS_DISK', 'public'),
                    $this->excel,
                    (int) $request->user()->id
                )
                : null,
            'soas'                 => $this->whenLoaded('soas', fn () =>
                $this->soas->map(fn ($soa) => [
                    'id'             => $soa->id,
                    'soa_number'     => $soa->soa_number,
                    'amount'         => $soa->amount,
                    'applied_amount' => $soa->pivot->applied_amount ?? null,
                ])
            ),
            'activities'           => $this->whenLoaded('activities', fn () =>
                $this->activities->map(fn ($activity) => [
                    'id'         => $activity->id,
                    'event'      => $activity->event,
                    'from'       => $activity->from,
                    'to'         => $activity->to,
                    'actor'      => $activity->name,
                    'created_at' => CommonHelper::formatDate($activity->created_at),
                ])
            ),
            'deleted_at'           => $this->deleted_at,
        ];
    }
}
