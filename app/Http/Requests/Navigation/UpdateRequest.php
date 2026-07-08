<?php

namespace App\Http\Requests\Navigation;

use App\Enums\Status;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Validation rules for updating a navigation item (id, name, optional label, icon and status).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer'
            ],
            'name' => [
                'required',
                'string',
                'max:191'
            ],
            'label' => [
                'nullable',
                'string',
                'max:191'
            ],
            'icon' => [
                'nullable',
                'string',
                'max:191'
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in(Status::getValues())
            ],
        ];
    }
}
