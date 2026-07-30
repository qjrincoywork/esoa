<?php

namespace App\Http\Resources\Dashboard;

use App\Helpers\CommonHelper;
use App\Support\Dashboard\DashboardFilter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Billed vs collected value over time.
 *
 * Both series share one unit (peso amounts), which is what allows them on a single axis —
 * the chart deliberately never mixes a count series with a money series, since a second
 * y-scale would invent a correlation that is not in the data.
 */
class TimeSeriesResource extends JsonResource
{
    /**
     * @param array<int, array<string, mixed>> $resource Zero-filled period rows.
     */
    public function __construct($resource, private readonly string $granularity)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the period rows into a {points, series} payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'granularity' => $this->granularity,
            'granularity_label' => $this->granularity === DashboardFilter::GRANULARITY_DAY ? 'Daily' : 'Monthly',
            'series' => [
                [
                    'key' => 'billed',
                    'label' => 'Billed',
                    'token' => 'series-1',
                ],
                [
                    'key' => 'collected',
                    'label' => 'Collected',
                    'token' => 'series-2',
                ],
            ],
            'points' => array_map(static fn (array $point): array => [
                'key' => $point['key'],
                'label' => $point['label'],
                'count' => (int) $point['count'],
                'billed' => (float) $point['billed'],
                'collected' => (float) $point['collected'],
                'billed_formatted' => CommonHelper::formatMoney($point['billed']),
                'collected_formatted' => CommonHelper::formatMoney($point['collected']),
            ], $this->resource),
        ];
    }
}
