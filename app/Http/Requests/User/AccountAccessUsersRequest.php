<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Users with account/branch access to copy from — the source list behind "Copy Access
 * From Another User", used by both the user edit form and the mapping pane.
 */
class AccountAccessUsersRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    /**
     * Validate the optional account-access user lookup filters: a name string,
     * a user ID to exclude, and pagination (page and per_page).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'exclude_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                // Each user carries all their access rows, so an unbounded page is costly.
                'max:' . config('vc.max_per_pages'),
            ],
        ];
    }

    /**
     * Custom validation messages for the excluded user ID lookup.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exclude_id.exists' => 'The user to exclude does not exist.',
        ];
    }
}
