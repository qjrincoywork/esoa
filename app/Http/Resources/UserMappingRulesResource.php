<?php

namespace App\Http\Resources;

use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a user's type allows in account/branch mapping.
 *
 * The mapping tab enables itself and caps its list from these, so the rule stays the
 * server's ({@see UserType::allowsAccountMapping()}, {@see UserType::accountMappingLimit()})
 * rather than being repeated in the client. Shared by the details payload and the mapping
 * tab's own payload, so a pane opened straight onto the mapping tab needs nothing else.
 *
 * Expects the user with `userDetail` loaded.
 */
class UserMappingRulesResource extends JsonResource
{
    /**
     * @return array{type: int|null, type_label: string|null, allows_account_mapping: bool, account_mapping_limit: int|null}
     */
    public function toArray(Request $request): array
    {
        $type = $this->userDetail?->type;

        return [
            'type' => $type !== null ? (int) $type : null,
            'type_label' => $type !== null ? UserType::label((int) $type) : null,
            'allows_account_mapping' => UserType::allowsAccountMapping($type),
            'account_mapping_limit' => UserType::accountMappingLimit($type),
        ];
    }
}
