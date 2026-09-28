<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate the target user ID and an optional array of existing web-guard
     * permission IDs to grant directly. An empty or absent set clears the user's
     * direct permissions.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions' => ['array'],
            'permissions.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * Custom validation messages for the single-user permission-assignment fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The User field is required',
            'user_id.integer' => 'The User field must be an integer',
            'user_id.exists' => 'The User field must be an existing user',
            'permissions.array' => 'The Permissions field must be an array',
            'permissions.*.integer' => 'Each permission ID must be an integer',
            'permissions.*.distinct' => 'A permission was selected more than once',
            'permissions.*.exists' => 'One or more selected permissions do not exist',
        ];
    }
}
