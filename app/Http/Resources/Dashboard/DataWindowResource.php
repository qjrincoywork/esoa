<?php

namespace App\Http\Resources\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The period the reported data actually spans.
 *
 * Shown when a filter returns nothing, so an empty dashboard says "there are no invoices in
 * this window — the data runs from X to Y" instead of leaving the reader to guess whether
 * the page is broken.
 */
class DataWindowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $first = Carbon::parse($this->resource['first_at']);
        $last = Carbon::parse($this->resource['last_at']);

        return [
            'first_at' => $first->toDateString(),
            'last_at' => $last->toDateString(),
            'label' => $first->format('M j, Y') . ' – ' . $last->format('M j, Y'),
        ];
    }
}
