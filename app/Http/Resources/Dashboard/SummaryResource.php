<?php

namespace App\Http\Resources\Dashboard;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Headline figures of the dashboard, shaped as ready-to-render stat tiles.
 *
 * Each tile ships the raw number (for the chart layer and for client-side compacting)
 * next to the formatted string (so the value is always readable without JS number
 * formatting), plus the semantic tone the tile should wear.
 */
class SummaryResource extends JsonResource
{
    /**
     * Transform the aggregate row into the KPI payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $billed = (float) ($this->resource['billed_amount'] ?? 0);
        $collected = (float) ($this->resource['collected_amount'] ?? 0);
        $outstanding = (float) ($this->resource['outstanding_amount'] ?? 0);
        $pastDueCount = (int) ($this->resource['past_due_count'] ?? 0);
        $pastDueOutstanding = (float) ($this->resource['past_due_outstanding_amount'] ?? 0);
        $collectionRate = $billed > 0 ? round(($collected / $billed) * 100, 1) : 0.0;

        return [
            'outstanding' => [
                'key' => 'outstanding',
                'label' => 'Outstanding balance',
                'hint' => 'Unpaid, endorsed and disputed invoices',
                'value' => $outstanding,
                'formatted' => CommonHelper::formatMoney($outstanding),
                'format' => 'currency',
                'tone' => $outstanding > 0 ? 'serious' : 'good',
            ],
            'billed' => [
                'key' => 'billed',
                'label' => 'Total billed',
                'hint' => 'Value of invoices in the selected period',
                'value' => $billed,
                'formatted' => CommonHelper::formatMoney($billed),
                'format' => 'currency',
                'tone' => 'neutral',
            ],
            'collected' => [
                'key' => 'collected',
                'label' => 'Collected',
                'hint' => 'Value of invoices marked paid',
                'value' => $collected,
                'formatted' => CommonHelper::formatMoney($collected),
                'format' => 'currency',
                'tone' => 'good',
            ],
            'collection_rate' => [
                'key' => 'collection_rate',
                'label' => 'Collection rate',
                'hint' => 'Collected against total billed',
                'value' => $collectionRate,
                'formatted' => $collectionRate . '%',
                'format' => 'percent',
                'tone' => $collectionRate >= 75 ? 'good' : ($collectionRate >= 40 ? 'warning' : 'serious'),
            ],
            'invoices' => [
                'key' => 'invoices',
                'label' => 'Billing invoices',
                'hint' => (int) ($this->resource['account_count'] ?? 0) . ' account(s) billed',
                'value' => (int) ($this->resource['invoice_count'] ?? 0),
                'formatted' => number_format((int) ($this->resource['invoice_count'] ?? 0)),
                'format' => 'number',
                'tone' => 'neutral',
            ],
            // Counts every invoice sitting in a past-due aging bucket (any status), so the
            // tile equals the past-due bars; the money beside it is what is still owed.
            'past_due' => [
                'key' => 'past_due',
                'label' => 'Past due invoices',
                'hint' => CommonHelper::formatMoney($pastDueOutstanding) . ' still unsettled',
                'value' => $pastDueCount,
                'formatted' => number_format($pastDueCount),
                'format' => 'number',
                'tone' => $pastDueCount > 0 ? 'critical' : 'good',
            ],
        ];
    }
}
