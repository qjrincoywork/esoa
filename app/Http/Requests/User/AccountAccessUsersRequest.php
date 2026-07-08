<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class AccountAccessUsersRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

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
