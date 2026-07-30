<?php

namespace App\Http\Requests\Role;

use App\Rules\IsDataExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Validate that the target role ID exists (via the IsDataExists rule) and that
     * a name and guard name are provided.
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
            ],
            'name' => [
                'required',
                'string',
                'max:191'
            ],
            'guard_name' => [
                'required',
                'string',
                'max:191'
            ],
        ];
    }
}
