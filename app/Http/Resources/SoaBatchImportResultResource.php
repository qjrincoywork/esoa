<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the outcome of a batch billing-invoice upload for the client.
 *
 * Wraps the plain summary array produced by {@see \App\Services\SoaBatchImportService::import()}
 * into a stable response envelope of counts plus a per-row error list. Because the
 * import is all-or-nothing, a response carrying any errors has created nothing: the
 * counts are there so the client can say how much of the file needs fixing.
 */
class SoaBatchImportResultResource extends JsonResource
{
    /**
     * Transform the import summary into its response representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->resource['total'] ?? 0,
            'created' => $this->resource['created'] ?? 0,
            'failed' => $this->resource['failed'] ?? 0,
            'errors' => $this->resource['errors'] ?? [],
        ];
    }
}
