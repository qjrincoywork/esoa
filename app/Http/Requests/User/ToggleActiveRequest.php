<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ToggleActiveRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate that the target user ID exists and the boolean active status to set.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id'        => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Custom validation messages for the user active-status toggle fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required'        => 'User ID is required.',
            'id.exists'          => 'User not found.',
            'is_active.required' => 'Active status is required.',
            'is_active.boolean'  => 'Active status must be true or false.',
        ];
    }
}
