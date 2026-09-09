<?php

namespace App\Http\Resources;

use App\Enums\UserType;
use App\Helpers\CommonHelper;
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
            'created_at'    => CommonHelper::formatDate($this->created_at),
            // Sorting and CSV export need the orderable value, not the display label.
            'created_at_value' => $this->created_at?->toDateTimeString(),
            'type'          => $detail?->type,
            'type_label'    => $detail?->type !== null ? UserType::label((int) $detail->type) : null,
            // Whether mappings apply to this type, so the row can hide the mapping action.
            'allows_account_mapping' => UserType::allowsAccountMapping($detail?->type),
            'department_id' => $detail?->department_id,
            'department'    => $detail?->department?->name,
        ];
    }
}
