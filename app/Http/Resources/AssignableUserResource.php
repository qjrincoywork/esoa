<?php

namespace App\Http\Resources;

use App\Enums\MappingEligibility;
use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One user as the "Assign users" picker lists them: who they are, how many mappings
 * they hold against their type's cap, and whether they can take the selected rows.
 *
 * The counts are attached per page by the controller
 * ({@see \App\Models\UserAccount::mappingSummaryFor()}) as `mapping_count` and
 * `held_count`, and `requested_count` is how many distinct rows were selected; nothing
 * is counted here, which would be a query per user.
 */
class AssignableUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->userDetail?->type;
        $mapped = (int) ($this->mapping_count ?? 0);
        $held = (int) ($this->held_count ?? 0);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'full_name' => $this->userDetail?->full_name,
            'is_active' => (bool) $this->is_active,
            'type' => $type !== null ? (int) $type : null,
            'type_label' => UserType::label((int) $type),
            'mapping_count' => $mapped,
            'mapping_limit' => UserType::accountMappingLimit($type),
            // How many of the selected rows the user already holds.
            'held_count' => $held,
            'eligibility' => MappingEligibility::present(
                MappingEligibility::resolve($type, $mapped, $held, (int) ($this->requested_count ?? 0))
            ),
        ];
    }
}
