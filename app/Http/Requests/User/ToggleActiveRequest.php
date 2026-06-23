<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class ToggleActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'id'        => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

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
