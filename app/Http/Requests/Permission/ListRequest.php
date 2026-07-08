<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Validation rules for filtering, sorting and paginating the permission listing.
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
            'sort_by' => [
                'nullable',
                'string',
                'in:name,guard_name,created_at'
            ],
            'sort_direction' => [
                'nullable',
                'string',
                'in:asc,desc'
            ],
        ];
    }
}
