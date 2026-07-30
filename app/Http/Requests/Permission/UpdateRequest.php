<?php

namespace App\Http\Requests\Permission;

use App\Rules\IsDataExists;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Validation rules for updating a permission's name and guard name.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsDataExists('permissions'),
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
