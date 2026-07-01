<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'action'     => ['required', 'in:delete,restore'],
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
            'action.required'     => 'The action is required.',
            'action.in'           => 'The action must be either delete or restore.',
        ];
    }
}
