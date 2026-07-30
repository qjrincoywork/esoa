<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate a non-empty list of existing (non-soft-deleted) user IDs and an
     * action of either "delete" or "restore".
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'action'     => ['required', 'in:delete,restore'],
        ];
    }

    /**
     * Custom validation messages for the bulk delete/restore fields.
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
            'action.required'     => 'The action is required.',
            'action.in'           => 'The action must be either delete or restore.',
        ];
    }
}
