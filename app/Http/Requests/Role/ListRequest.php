<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Validate the optional role listing filters: a search string (max 100 chars)
     * and a page size.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => [
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
