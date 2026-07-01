<?php

namespace App\Http\Requests\NavigationModule;

use App\Rules\{IsDataExists, IsUserAdmin};
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
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
