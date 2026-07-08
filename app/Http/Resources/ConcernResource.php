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
            'billing_invoice' => implode(', ', $this->soas->pluck('soa_number')->toArray()),
            'type' => ConcernType::label((int) $this->type),
            'title' => $this->title,
            'description' => $this->description,
            'status' => TicketStatus::label((int) $this->status),
            'status_color' => TicketStatus::color((int) $this->status),
            'attachment' => $this->attachment,
            'attachment_preview_token' => $this->attachment && $request->user()
                ? CommonHelper::createFilePreviewToken(
                    config('vc.disks.concerns'),
                    $this->attachment,
                    (int) $request->user()->id
                )
                : null,
            'created_by' => $this->resource->user->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at),
        ];
    }
}
