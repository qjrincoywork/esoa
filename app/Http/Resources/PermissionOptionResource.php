<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One permission as the user-permission picker lists it.
 *
 * Permission names follow the route-name convention `module.action`
 * ({@see \App\Http\Middleware\CheckPermission}), so the module segment is used as the
 * group the picker sections the list by and the action as the row label. New
 * permissions therefore land in the right section without any extra configuration;
 * a name without a dot forms its own group.
 */
class PermissionOptionResource extends JsonResource
{
    /**
     * Transform the permission into a picker option.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        [$group, $action] = str_contains($this->name, '.')
            ? explode('.', $this->name, 2)
            : [$this->name, $this->name];

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'group'       => $group,
            'group_label' => Str::headline($group),
            'label'       => Str::headline($action),
        ];
    }
}
