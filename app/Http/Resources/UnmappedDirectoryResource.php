<?php

namespace App\Http\Resources;

use App\Enums\AccountDirectoryScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the combined unmapped listing, accounts and branches together.
 *
 * The listing stacks both kinds into one shape and says which each row is
 * ({@see \App\Helpers\SqlDatabase::getUnassignedDirectoryByParams()}); this hands each row
 * to its own kind's resource, so a row reads exactly as it would in the single-kind
 * listing — {@see UnmappedAccountResource} or {@see UnmappedBranchResource} — and the
 * two can never drift apart.
 *
 * Branch rows read their account's name from the memo, so prime the page's account codes
 * ({@see \App\Helpers\CommonHelper::primeAccountNames()}) before serialising, as for the
 * branch listing.
 */
class UnmappedDirectoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = ($this->resource->kind ?? null) === AccountDirectoryScope::BRANCH
            ? new UnmappedBranchResource($this->resource)
            : new UnmappedAccountResource($this->resource);

        return $row->toArray($request);
    }
}
