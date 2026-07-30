<?php

namespace App\Http\Requests\NavigationModule;

use App\Rules\{IsDataExists, IsUserAdmin};
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Validation rules for deleting a navigation module, ensuring it exists and the user is an admin.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsDataExists('navigation_modules'),
                new IsUserAdmin(),
            ],
        ];
    }
}
