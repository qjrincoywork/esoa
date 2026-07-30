<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One bar / slice / card of a bucketed dashboard chart.
 *
 * Handles both bucket kinds behind a single shape so the chart components stay generic:
 * an aging bucket adds the `emphasis` flag (past due or not) and a status bucket adds the
 * semantic `tone`. Labels, badge classes and the drill-through link all come from the
 * enums, which remain the single source of truth.
 */
class MetricBucketResource extends JsonResource
{
    /**
     * Transform an aggregated bucket into the chart/table payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = (int) $this->resource['value'];
        $isStatus = ($this->resource['type'] ?? null) === 'status';
        $amount = (float) ($this->resource['amount'] ?? 0);

        return [
            'key' => ($isStatus ? 'status' : 'aging') . '-' . $value,
            'type' => $isStatus ? 'status' : 'aging',
            'value' => $value,
            'label' => $isStatus ? SoaStatus::label($value) : SoaAging::label($value),
            'count' => (int) ($this->resource['count'] ?? 0),
            'amount' => $amount,
            'amount_formatted' => CommonHelper::formatMoney($amount),
            'badge_class' => $isStatus ? SoaStatus::color($value) : SoaAging::color($value),
            'tone' => $isStatus ? SoaStatus::tone($value) : null,
            'emphasis' => $isStatus ? true : SoaAging::isPastDue($value),
            'href' => $isStatus
                ? route('soas.list', ['status' => $value])
                : SoaAging::listUrl($value),
        ];
    }
}
