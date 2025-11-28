<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NavigationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource['data']->items(),
            'current_page' => $this->resource['data']->currentPage(),
            'from' => $this->resource['data']->firstItem(),
            'last_page' => $this->resource['data']->lastPage(),
            'links' => $this->resource['data']->links(),
            'path' => $this->resource['data']->path(),
            'per_page' => $this->resource['data']->perPage(),
            'to' => $this->resource['data']->lastItem(),
            'total' => $this->resource['data']->total(),
        ];
    }
}
