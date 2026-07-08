<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkToggleActiveRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate a non-empty list of existing user IDs and the target boolean
     * active status to apply to them.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'is_active'  => ['required', 'boolean'],
        ];
    }

    /**
     * Custom validation messages for the bulk active-status toggle fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required'   => 'Please select at least one user.',
            'user_ids.array'      => 'Users must be provided as an array.',
            'user_ids.min'        => 'Please select at least one user.',
            'user_ids.*.required' => 'Each user entry is required.',
            'user_ids.*.integer'  => 'Each user ID must be an integer.',
            'user_ids.*.exists'   => 'One or more selected users do not exist.',
            'is_active.required'  => 'The active status is required.',
            'is_active.boolean'   => 'The active status must be true or false.',
        ];
    }
}
