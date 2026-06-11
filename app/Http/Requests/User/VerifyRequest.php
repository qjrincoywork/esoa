<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

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
