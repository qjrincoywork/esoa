<?php

namespace App\Http\Requests\Navigation;

use App\Enums\Status;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    /**
     * Validation rules for creating a navigation item (name, optional label, icon and status).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
