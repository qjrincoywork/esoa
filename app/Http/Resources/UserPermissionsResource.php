<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's permission state for the direct-permission form.
 *
 * Separates what is granted to the user directly — the only thing the form edits —
 * from what the user already inherits through a role, so the form can flag a
 * permission as held regardless and explain that removing the direct grant alone
 * will not take it away. Expects `permissions` and `roles.permissions` to be loaded.
 */
class UserPermissionsResource extends JsonResource
{
    /**
     * Transform the user into its direct and inherited permission state.
     *
     * `inherited_permissions` maps a permission id to the names of the roles that grant
     * it, so the form looks a permission up rather than scanning every role.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inherited = [];

        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                $inherited[$permission->id][] = $role->name;
            }
        }

        return [
            'id'                     => $this->id,
            'username'               => $this->username,
            'email'                  => $this->email,
            'roles'                  => $this->roles->pluck('name')->values(),
            'direct_permission_ids'  => $this->permissions->pluck('id')->values(),
            // An empty PHP array would serialise as [] and lose its map shape client-side.
            'inherited_permissions'  => (object) $inherited,
        ];
    }
}
