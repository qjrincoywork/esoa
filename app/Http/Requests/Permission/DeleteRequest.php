<?php

namespace App\Http\Requests\Permission;

use App\Rules\{ IsDataExists, IsUserAdmin };
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
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
                new IsUserAdmin()
            ],
        ];
    }
}
