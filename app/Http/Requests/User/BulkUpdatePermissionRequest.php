<?php

namespace App\Http\Requests\User;

use App\Enums\PermissionAssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdatePermissionRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" or "admin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['superadmin', 'admin']) ?? false;
    }

    /**
     * Validate a non-empty list of existing user IDs, the assignment mode, and the
     * permission IDs to apply. Adding or removing needs at least one permission; an
     * empty replace is allowed and clears every selected user's direct permissions.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'mode' => ['required', 'string', Rule::in(PermissionAssignmentMode::getValues())],
            'permissions' => [
                Rule::requiredIf(fn (): bool => PermissionAssignmentMode::requiresSelection($this->input('mode'))),
                'array',
            ],
            'permissions.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * Custom validation messages for the bulk permission-assignment fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Please select at least one user.',
            'user_ids.array' => 'Users must be provided as an array.',
            'user_ids.min' => 'Please select at least one user.',
            'user_ids.*.required' => 'Each user entry is required.',
            'user_ids.*.integer' => 'Each user ID must be an integer.',
            'user_ids.*.distinct' => 'A user was selected more than once.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
            'mode.required' => 'Please choose how the permissions should be applied.',
            'permissions.required' => 'Please select at least one permission to add or remove.',
            'permissions.array' => 'Permissions must be provided as an array.',
            'permissions.*.integer' => 'Each permission ID must be an integer.',
            'permissions.*.distinct' => 'A permission was selected more than once.',
            'permissions.*.exists' => 'One or more selected permissions do not exist.',
        ];
    }
}
