<?php

namespace App\Http\Resources;

use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use App\Helpers\CommonHelper;
use App\Helpers\SqlDatabase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SoaAgingCountResource extends JsonResource
{
    /**
     * Transform an aggregated count bucket into an array, resolving the label, color and
     * list-page link from either the status or aging enum depending on the bucket type.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $value = $this->resource['value'];
        $isStatus = ($this->resource['type'] ?? null) === 'status';

        return [
            'type' => $isStatus ? 'status' : 'aging',
            'value' => $value,
            'count' => $this->resource['count'],
            'label' => $isStatus ? SoaStatus::label($value) : SoaAging::label($value),
            'color' => $isStatus ? SoaStatus::color($value) : SoaAging::color($value),
            'href' => $isStatus
                ? route('soas.list', ['status' => $value])
                : SoaAging::listUrl($value),
        ];
    }
}
