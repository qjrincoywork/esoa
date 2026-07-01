<?php

namespace App\Http\Requests\User;

use App\Enums\{IsActive, UserType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => ['nullable', 'string', 'max:' . config('vc.max_string_limit')],
            'per_page'      => ['nullable', 'integer'],
            'type'          => ['nullable', 'integer', Rule::in(UserType::getValues())],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'     => ['nullable', 'integer', Rule::in(IsActive::getValues())],
        ];
    }
}
