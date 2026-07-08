<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateRoleRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate a non-empty list of existing user IDs and an optional array of
     * existing role IDs to assign to them.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'roles'      => ['array'],
            'roles.*'    => ['integer', 'exists:roles,id'],
        ];
    }

    /**
     * Custom validation messages for the bulk role-assignment fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required'  => 'Please select at least one user.',
            'user_ids.array'     => 'Users must be provided as an array.',
            'user_ids.min'       => 'Please select at least one user.',
            'user_ids.*.required' => 'Each user entry is required.',
            'user_ids.*.integer' => 'Each user ID must be an integer.',
            'user_ids.*.exists'  => 'One or more selected users do not exist.',
            'roles.array'        => 'Roles must be provided as an array.',
            'roles.*.integer'    => 'Each role ID must be an integer.',
            'roles.*.exists'     => 'One or more selected roles do not exist.',
        ];
    }
}
