<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    /**
     * Validate a new role: a unique name that starts with a letter (3-191 chars),
     * a required guard name, and an optional array of existing permission IDs.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:191',
                'min:3',
                Rule::unique('roles', 'name'),
                // 'not_in:' . $this->getProtectedRolesForValidation(),
                'regex:/^[a-zA-Z][a-zA-Z0-9\s\_\-]*$/' // Must start with a letter
            ],
            'guard_name' => [
                'required',
                'string',
                'max:191'
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id']
        ];
    }
}
