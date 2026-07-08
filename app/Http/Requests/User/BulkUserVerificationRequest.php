<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkUserVerificationRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate a non-empty list of existing user IDs to mark as verified.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Custom validation messages for the bulk user-verification fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required'  => 'Please select at least one user.',
            'user_ids.array'     => 'Users must be provided as an array.',
            'user_ids.min'       => 'Please select at least one user.',
            'user_ids.*.exists'  => 'One or more selected users do not exist.',
        ];
    }
}
