<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'nullable',
                'string',
                'max:100'
            ],
            'email' => [
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
