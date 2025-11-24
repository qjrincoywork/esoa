<?php

namespace App\Http\Requests\User;

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
            'search_string' => [
                'nullable',
                'string',
                'max:191'
            ],
            'per_page' => [
                'nullable',
                'integer'
            ],
        ];
    }
}
