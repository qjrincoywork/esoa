<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\UserType;
use App\Models\User;
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
                'max:' . config('vc.max_string_limit'),
                //new IsDataExists('branches'),
            ],
            'agent_code' => [
                'nullable',
                'required_if:type,' . UserType::BROKER,
                'string',
                'max:' . config('vc.max_string_limit'),
                //new IsDataExists('agents'),
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
                'max:' . config('vc.max_string_limit'),
            ],
            'first_name' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'middle_name' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'last_name' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'suffix' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
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
                'max:' . config('vc.max_string_limit'),
            ],
            'username' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
                'min:' . config('vc.min_username_string_limit'),
                // Starts with a letter
                // Only letters, numbers, _, ., -
                // No consecutive symbols
                // Ends with a letter or number
                'regex:/^(?=.{3,30}$)[A-Za-z](?!.*[._-]{2})[A-Za-z0-9._-]*[A-Za-z0-9]$/',
                'max:' . config('vc.max_string_limit'),
                // Reserved words
                Rule::notIn(config('vc.reserved_usernames')),
                'unique:users,username',
            ],
            'email' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
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
            'agent_code.required_if' => 'The Agent Code field is required',
        ];
    }
}
