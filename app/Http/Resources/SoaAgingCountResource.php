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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource['value'],
            'count' => $this->resource['count'],
            'label' => SoaAging::label($this->resource['value']),
            'color' => SoaAging::color($this->resource['value']),
        ];
    }
}
