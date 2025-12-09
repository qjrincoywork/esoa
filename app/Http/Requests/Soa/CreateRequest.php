<?php

namespace App\Http\Requests\User;

use App\Rules\IsDataExists;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'gender_id' => [
                'required',
                'integer',
                new IsDataExists('genders'),
            ],
            'civil_status_id' => [
                'required',
                'integer',
                new IsDataExists('civil_statuses'),
            ],
            'citizenship_id' => [
                'required',
                'integer',
                new IsDataExists('citizenships'),
            ],
            'department_id' => [
                'required',
                'integer',
                new IsDataExists('departments'),
            ],
            'position_id' => [
                'required',
                'integer',
                new IsDataExists('positions'),
            ],
            'first_name' => [
                'required',
                'string',
                'max:191'
            ],
            'last_name' => [
                'required',
                'string',
                'max:191'
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:191'
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:191'
            ],
            'birthdate' => [
                'nullable',
                'date',
                'max:191'
            ],
            'employee_no' => [
                'nullable',
                'string',
                'max:191'
            ],
            'username' => [
                'required',
                'string',
                'max:191'
            ],
            'email' => [
                'required',
                'string',
                'max:191'
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gender_id.required' => 'The Gender field is required',
            'civil_status_id.required' => 'The Civil Status field is required',
            'citizenship_id.required' => 'The Citizenship field is required',
            'department_id.required' => 'The Department field is required',
            'position_id.required' => 'The Position field is required',
        ];
    }
}
