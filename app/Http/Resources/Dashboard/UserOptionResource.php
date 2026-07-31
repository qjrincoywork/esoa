<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\UserType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as an option of the dashboard's "viewing" filter.
 *
 * Uses the {value, name} shape every combobox in the app already speaks.
 */
class UserOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->userDetail?->type;

        return [
            'value' => (int) $this->id,
            'name' => $type !== null
                ? $this->username . ' — ' . UserType::label((int) $type)
                : (string) $this->username,
        ];
    }
}
