<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the outcome of a batch billing-invoice upload for the client.
 *
 * Wraps the plain summary array produced by {@see \App\Services\SoaBatchImportService::import()}
 * into a stable response envelope of counts plus a per-row error list. By default the
 * import is all-or-nothing, so a response carrying any errors has created nothing —
 * but a request made with `skip_errors` can come back with both `created` and `failed`
 * greater than zero, which `partial` names explicitly rather than leaving the client to
 * infer it from the two counts.
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
        $created = $this->resource['created'] ?? 0;
        $failed = $this->resource['failed'] ?? 0;

        return [
            'total' => $this->resource['total'] ?? 0,
            'created' => $created,
            'failed' => $failed,
            'partial' => $created > 0 && $failed > 0,
            'errors' => $this->resource['errors'] ?? [],
        ];
    }
}
