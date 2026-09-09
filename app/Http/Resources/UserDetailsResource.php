<?php

namespace App\Http\Resources;

use App\Enums\Gender;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as the right-pane "User Details" tab reads them.
 *
 * Codes are resolved to the labels the forms use, and the type's mapping rules travel
 * with the payload so the mapping tab can enable itself and cap its list from what the
 * server already decided — {@see UserType::accountMappingLimit()} — rather than
 * repeating the rule in the client.
 */
class UserDetailsResource extends JsonResource
{
    /**
     * Transform the user into the pane's details payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detail = $this->userDetail;
        $type = $detail?->type;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->composeFullName($detail),
            'is_active' => (bool) $this->is_active,
            'is_approved' => (bool) $this->is_approved,
            'is_verified' => $this->email_verified_at !== null,
            'email_verified_at' => $this->email_verified_at?->toDayDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDayDateTimeString(),
            'created_at' => $this->created_at?->toDayDateTimeString(),

            'type' => $type !== null ? (int) $type : null,
            'type_label' => $type !== null ? UserType::label((int) $type) : null,
            // Mapping rules for this type, so the mapping tab needs no copy of them.
            'allows_account_mapping' => UserType::allowsAccountMapping($type),
            'account_mapping_limit' => UserType::accountMappingLimit($type),

            'employee_no' => $detail?->employee_no,
            'agent_code' => $detail?->agent_code,
            'birthdate' => $detail?->birthdate,
            'gender' => Gender::hasValue((int) $detail?->gender_id)
                ? Gender::label((int) $detail->gender_id)
                : null,
            'civil_status' => $detail?->civil_status?->name,
            'citizenship' => $detail?->citizenship?->name,
            'department' => $detail?->department?->name,
            'position' => $detail?->position?->name,

            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->pluck('name')->values(),
                []
            ),
        ];
    }

    /**
     * Compose "First Middle Last, Suffix" from whichever name parts are stored.
     */
    private function composeFullName($detail): ?string
    {
        if (!$detail) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $detail->first_name,
            $detail->middle_name,
            $detail->last_name,
        ])));

        if ($name === '') {
            return null;
        }

        return $detail->suffix ? "{$name}, {$detail->suffix}" : $name;
    }
}
