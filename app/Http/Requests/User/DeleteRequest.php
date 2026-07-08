<?php

namespace App\Http\Requests\User;

use App\Rules\{ IsDataExists, IsUserAdmin };
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Validate that the target user ID exists (via the IsDataExists rule) and that
     * the current user is an admin (via the IsUserAdmin rule) before deletion.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsDataExists('users'),
                new IsUserAdmin()
            ],
        ];
    }
}
