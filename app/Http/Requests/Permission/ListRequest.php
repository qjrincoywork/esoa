<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:100'
            ],
            'guard_name' => [
                'nullable',
                'string',
                'max:100'
            ],
            'per_page' => [
                'nullable',
                'integer'
            ],
        ];
    }
}
