<?php

namespace App\Http\Resources;

use App\Enums\BillType;
use App\Enums\ConcernType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Enums\TicketStatus;
use App\Helpers\CommonHelper;
use App\Helpers\SqlDatabase;
use App\Models\Concern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ConcernResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_invoice' => $this->billing_invoice,
            'type' => ConcernType::label($this->type),
            'title' => $this->title,
            'description' => $this->description,
            'status' => TicketStatus::label($this->status),
            'status_color' => TicketStatus::color($this->status),
            'attachment' => $this->attachment,
            'created_by' => $this->resource->user->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at),
        ];
    }
}
