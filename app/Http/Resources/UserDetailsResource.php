<?php

namespace App\Http\Resources;

use App\Enums\Gender;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as the right-pane "User Details" tab reads them.
 *
 * Codes are resolved to the labels the forms use, and the type's mapping rules travel
 * with the payload ({@see UserMappingRulesResource}) so the pane can tell whether the
 * mapping tab applies without fetching it. Nothing here asks HMS: the mappings are only
 * counted, and are labelled by the mapping tab's own request when it is opened.
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

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $detail?->full_name,
            'is_active' => (bool) $this->is_active,
            'is_approved' => (bool) $this->is_approved,
            'is_verified' => $this->email_verified_at !== null,
            'email_verified_at' => $this->email_verified_at?->toDayDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDayDateTimeString(),
            'created_at' => $this->created_at?->toDayDateTimeString(),

            // The type, and its mapping rules, so the mapping tab needs no copy of them.
            ...(new UserMappingRulesResource($this->resource))->toArray($request),
            // How many mappings the user holds — counted, not loaded, so the details tab
            // can say so without labelling every one from HMS. Present only when counted.
            // (The SQL Server driver hands counts back as strings.)
            'account_mapping_count' => $this->whenCounted('userAccounts', fn ($count) => (int) $count),

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

            'credentials' => new UserCredentialResource($this->resource),
        ];
    }
}
