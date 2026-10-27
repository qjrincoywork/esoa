<?php

namespace App\Http\Resources;

use App\Enums\AdvisoryState;
use App\Helpers\CommonHelper;
use App\Support\LoginAdvisories;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvisoryResource extends JsonResource
{
    /** Format the datetime-local input reads and writes. */
    private const INPUT_FORMAT = 'Y-m-d\TH:i';

    /**
     * Transform the advisory into an array for the admin listing and form: its content,
     * its window both formatted for display and raw for the datetime inputs, and the
     * state it is in right now.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource->state();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'image' => $this->image,
            'image_url' => LoginAdvisories::imageUrl($this->resource),
            'is_active' => (int) $this->is_active,
            'starts_at' => CommonHelper::formatDate($this->starts_at, true),
            'ends_at' => CommonHelper::formatDate($this->ends_at, true),
            'starts_at_value' => $this->starts_at?->format(self::INPUT_FORMAT),
            'ends_at_value' => $this->ends_at?->format(self::INPUT_FORMAT),
            'state' => AdvisoryState::label($state),
            'state_value' => $state,
            'state_color' => AdvisoryState::color($state),
            'created_by' => $this->resource->creator->username ?? null,
            'updated_by' => $this->resource->updater->username ?? null,
            'created_at' => CommonHelper::formatDate($this->created_at, true),
            'updated_at' => CommonHelper::formatDate($this->updated_at, true),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
