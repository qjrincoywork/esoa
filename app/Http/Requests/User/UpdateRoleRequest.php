<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Validate the target user ID and an optional array of existing role IDs to
     * assign to that user.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'roles' => [
                'array',
            ],
            'roles.*' => [
                'integer',
                'exists:roles,id',
            ],
        ];
    }

    /**
     * Custom validation messages for the single-user role-assignment fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The User field is required',
            'user_id.integer' => 'The User field must be an integer',
            'user_id.exists' => 'The User field must be an existing user',
            'roles.array' => 'The Roles field must be an array',
            'roles.*.integer' => 'The Roles field must be an integer',
            'roles.*.exists' => 'The Roles field must be an existing role',
        ];
    }
}
