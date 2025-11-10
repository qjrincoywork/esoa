<?php

namespace App\Http\Requests\User;

use App\Rules\IsDataExists;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
                'integer'
            ],
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
                'required',
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
            // 'username' => [
            //     'nullable',
            //     'string',
            //     'max:100'
            // ],
            // 'email' => [
            //     'nullable',
            //     'string',
            //     'max:100'
            // ],
        ];
    }
}
