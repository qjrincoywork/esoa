<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate a non-empty list of existing user IDs to verify.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Custom validation messages for the user-verification fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required'  => 'At least one user must be selected.',
            'ids.array'     => 'Invalid user selection.',
            'ids.min'       => 'At least one user must be selected.',
            'ids.*.exists'  => 'One or more selected users do not exist.',
        ];
    }
}
