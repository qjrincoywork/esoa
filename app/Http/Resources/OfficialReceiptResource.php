<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfficialReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'or_number'          => $this->or_number,
            'or_date'            => CommonHelper::formatDate($this->or_date),
            'amount'             => $this->amount,
            'remarks'            => $this->remarks,
            'issued_by'          => $this->resource->user->username ?? null,
            'created_at'         => CommonHelper::formatDate($this->created_at),
            'account_payment_id' => $this->account_payment_id,
            'file'               => $this->file,
            'file_preview_token' => $this->file && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    env('OFFICIAL_RECEIPTS_DISK', 'public'),
                    $this->file,
                    (int) $request->user()->id
                )
                : null,
            'soas'               => $this->whenLoaded('soas', fn () =>
                $this->soas->map(fn ($soa) => [
                    'id'         => $soa->id,
                    'soa_number' => $soa->soa_number,
                    'amount'     => $soa->amount,
                    'status'     => $soa->status,
                ])
            ),
            'deleted_at'         => $this->deleted_at,
        ];
    }
}
