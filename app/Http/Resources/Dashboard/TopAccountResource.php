<?php

namespace App\Http\Resources\Dashboard;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the "accounts carrying the largest balance" ranking.
 *
 * The account name is resolved once, in bulk, by the service — never per row here, so the
 * ranking never turns into a lookup-per-bar.
 */
class TopAccountResource extends JsonResource
{
    /**
     * Transform an aggregated account row into the ranking payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accountCode = (string) $this->resource['account_code'];
        $outstanding = (float) ($this->resource['outstanding_amount'] ?? 0);
        $billed = (float) ($this->resource['billed_amount'] ?? 0);

        return [
            'key' => $accountCode,
            'account_code' => $accountCode,
            'label' => CommonHelper::convertStringEncoding($this->resource['account_name'] ?? null) ?: $accountCode,
            'count' => (int) ($this->resource['count'] ?? 0),
            'billed_amount' => $billed,
            'billed_formatted' => CommonHelper::formatMoney($billed),
            'outstanding_amount' => $outstanding,
            'outstanding_formatted' => CommonHelper::formatMoney($outstanding),
            'href' => route('soas.list', ['account_code' => $accountCode]),
        ];
    }
}
