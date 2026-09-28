<?php

namespace App\Http\Resources;

use App\Enums\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A navigation module as the module list, the navigation details pane and the edit
 * form read it. Foreign keys and status are integers so the client can compare them
 * strictly, and the navigation/permission names come along when they were eager-loaded.
 */
class NavigationModuleResource extends JsonResource
{
    /**
     * Transform the module into its list/form shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = (int) $this->status;

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'url'           => $this->url,
            'icon'          => $this->icon,
            'color'         => $this->color,
            'navigation_id' => $this->navigation_id,
            'permission_id' => $this->permission_id,
            'ref_id'        => $this->ref_id,
            'order_number'  => $this->order_number,
            'status'        => $status,
            'status_label'  => Status::hasValue($status) ? Status::label($status) : null,
            'deleted_at'    => $this->deleted_at,
            'navigation'    => $this->whenLoaded('navigation', fn () => $this->navigation?->only(['id', 'name'])),
            'permission'    => $this->whenLoaded('permission', fn () => $this->permission?->only(['id', 'name'])),
        ];
    }
}
