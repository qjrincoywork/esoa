<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkToggleActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
            'is_active'  => ['required', 'boolean'],
        ];
    }

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
