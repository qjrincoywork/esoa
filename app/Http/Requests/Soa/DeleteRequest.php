<?php

namespace App\Http\Requests\Soa;

use App\Rules\{ IsDataExists, IsUserAdmin };
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Validate that the id references an existing users record and that the acting user
     * is an admin.
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
