<?php

namespace App\Http\Requests\NavigationModule;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Validation rules for filtering and paginating the navigation module listing.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => ['nullable', 'string', 'max:191'],
            'navigation_id' => ['nullable', 'integer'],
            'per_page'      => ['nullable', 'integer'],
        ];
    }
}
