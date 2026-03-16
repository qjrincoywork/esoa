<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\BooleanAsInteger;
use App\Enums\UserType;
use App\Rules\IsDataExists;
use Illuminate\Validation\Rule;
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
            'type' => [
                'required',
                'integer',
                Rule::in(UserType::getValues()),
            ],
            'account_type' => [
                'required_if:type,' . UserType::ACCOUNT_BRANCH_ADMIN,
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'account_code' => [
                'required_if:type,' . UserType::ACCOUNT_BRANCH_ADMIN,
                'nullable',
                'string',
                //new IsDataExists('accounts'),
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:191'
                //new IsDataExists('branches'),
            ],
            'department_id' => [
                'required_if:type,' . UserType::VC_EMPLOYEE,
                'integer',
                new IsDataExists('departments'),
            ],
            'position_id' => [
                'required_if:type,' . UserType::VC_EMPLOYEE,
                'integer',
                new IsDataExists('positions'),
            ],
            'employee_no' => [
                'required_if:type,' . UserType::VC_EMPLOYEE,
                'string',
                'max:191'
            ],
            'first_name' => [
                'required',
                'string',
                'max:191'
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:191'
            ],
            'last_name' => [
                'required',
                'string',
                'max:191'
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:191'
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
            'birthdate' => [
                'nullable',
                'date',
                'max:191'
            ],
            'username' => [
                'required',
                'string',
                'max:191',
                'unique:users,username'
            ],
            'email' => [
                'required',
                'string',
                'max:191',
                'unique:users,email'
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
            'department_id.required_if' => 'The Department field is required',
            'employee_no.required_if' => 'The Employee No. field is required',
            'position_id.required_if' => 'The Position field is required',
            'account_type.required_if' => 'The Account Type field is required',
            'account_code.required_if' => 'The Account field is required',
        ];
    }
}
