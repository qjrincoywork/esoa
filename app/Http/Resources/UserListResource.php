<?php

namespace App\Http\Resources;

use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    /**
     * Transform the user into a list row, pulling type and department from the related
     * user detail and mapping the user type to its label when set.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->userDetail;

        return [
            'id'            => $this->id,
            'username'      => $this->username,
            'email'         => $this->email,
            'is_active'     => $this->is_active,
            'deleted_at'    => $this->deleted_at,
            'type'          => $detail?->type,
            'type_label'    => $detail?->type !== null ? UserType::label((int) $detail->type) : null,
            'department_id' => $detail?->department_id,
            'department'    => $detail?->department?->name,
        ];
    }
}
