<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the outcome of a bulk user import for the client.
 *
 * Wraps the plain summary array produced by {@see \App\Services\UserBulkImportService::import()}
 * into a stable response envelope of counts plus a per-row error list.
 */
class UserBulkImportResultResource extends JsonResource
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
