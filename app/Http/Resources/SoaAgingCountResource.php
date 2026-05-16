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
        $isStatus = in_array($this->resource['value'], [SoaStatus::ENDORSED, SoaStatus::DISPUTED]);

        return [
            'value' => $this->resource['value'],
            'count' => $this->resource['count'],
            'label' => $this->applyLabel($this->resource['value']),
            'color' => $this->applyColor($this->resource['value']),
            'href' => $this->redirectToSoaList($this->resource['value']),
        ];
    }


    private function redirectToSoaList($soaAgingValue)
    {
        $query = [];
        if (in_array($soaAgingValue, [SoaStatus::ENDORSED, SoaStatus::DISPUTED])) {
            $query['status'] = $soaAgingValue;
        } else {
            $query['due_in'] = $soaAgingValue;
        }

        return route('soas.index', $query);
    }

    private function applyLabel($soaAgingValue): string
    {
        if (in_array($soaAgingValue, [SoaStatus::ENDORSED, SoaStatus::DISPUTED])) {
            return SoaStatus::label($soaAgingValue);
        }

        return SoaAging::label($soaAgingValue);
    }

    private function applyColor($soaAgingValue): string
    {
        if (in_array($soaAgingValue, [SoaStatus::ENDORSED, SoaStatus::DISPUTED])) {
            return SoaStatus::color($soaAgingValue);
        }

        return SoaAging::color($soaAgingValue);
    }
}
