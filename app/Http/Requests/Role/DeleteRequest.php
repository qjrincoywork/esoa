<?php

namespace App\Http\Requests\Role;

use App\Rules\{ IsDataExists, IsUserAdmin };
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Validate that the target role ID exists in the roles table and that the
     * current user is an admin (via the IsUserAdmin rule) before deletion.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsDataExists('roles'),
                new IsUserAdmin()
            ],
        ];
    }
}
