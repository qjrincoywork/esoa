<?php

namespace App\Http\Requests\Navigation;

use App\Rules\{ IsDataExists, IsUserAdmin };
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Validation rules for deleting a navigation item, ensuring it exists and the user is an admin.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsDataExists('navigations'),
                new IsUserAdmin()
            ],
        ];
    }
}
