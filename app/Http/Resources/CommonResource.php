<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class CommonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $pagination = $this->resource->toArray();
        // dd($pagination, $this->resource);

        // return [
        //     'data' => $pagination['data'],
        //     'current_page' => $pagination['current_page'],
        //     'first_page_url' => $pagination['first_page_url'],
        //     'from' => $pagination['from'],
        //     'last_page' => $pagination['last_page'],
        //     'last_page_url' => $pagination['last_page_url'],
        //     'links' => $pagination['links'],
        //     'next_page_url' => $pagination['next_page_url'],
        //     'path' => $pagination['path'],
        //     'per_page' => $pagination['per_page'],
        //     'prev_page_url' => $pagination['prev_page_url'],
        //     'to' => $pagination['to'],
        //     'total' => $pagination['total'],
        // ];
        return [
            'data' => $this->resource->items(),
            'current_page' => $this->resource->currentPage(),
            'from' => $this->resource->firstItem(),
            // 'first_page_url' => $this->resource->firstPageUrl() ?? null,
            // 'last_page_url' => $this->resource->lastPageUrl(),
            'last_page' => $this->resource->lastPage(),
            'links' => $this->resource->links(),
            'next_page_url' => $this->resource->nextPageUrl(),
            'path' => $this->resource->path(),
            'prev_page_url' => $this->resource->previousPageUrl(),
            'per_page' => $this->resource->perPage(),
            'to' => $this->resource->lastItem(),
            'total' => $this->resource->total(),
        ];
    }

    /**
     * Get the status of the UP account
     *
     * @return string
     *   'Unpaid' if the account is unpaid and not endorsed
     *   'Endorsed' if the account is unpaid and endorsed
     *   'Paid' if the account is paid
     */
    public function status(): string
    {
        $status = (int) $this->resource->up_status;
        $endorsed = (int) $this->resource->up_endorsedtoacct;

        return match (true) {
            $status === 0 && $endorsed === 0 => 'Unpaid',
            $status === 0 && $endorsed === 1 => 'Endorsed',
            default => 'Paid',
        };
    }
}
