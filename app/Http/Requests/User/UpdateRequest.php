<?php

namespace App\Http\Requests\User;

use App\Enums\AccountType;
use App\Enums\BooleanAsInteger;
use App\Enums\Gender;
use App\Rules\IsDataExists;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        dd($this->all());
        return [
            'id' => [
                'required',
                'integer'
            ],
            'account_type' => [
                'required_if:is_vc_employee,' . BooleanAsInteger::FALSE,
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'account_code' => [
                'required_if:is_vc_employee,' . BooleanAsInteger::FALSE,
                'nullable',
                'string',
                //new IsDataExists('accounts'),
            ],
            'gender_id' => [
                'required',
                'integer',
                Rule::in(Gender::getValues()),
            ],
            'civil_status_id' => [
                'required',
                'integer',
                // new IsDataExists('civil_statuses'),
            ],
            'citizenship_id' => [
                'required',
                'integer',
                new IsDataExists('citizenships'),
            ],
            'department_id' => [
                'required_if:is_vc_employee,' . BooleanAsInteger::TRUE,
                'integer',
                new IsDataExists('departments'),
            ],
            'position_id' => [
                'required_if:is_vc_employee,' . BooleanAsInteger::TRUE,
                'integer',
                new IsDataExists('positions'),
            ],
            'employee_no' => [
                'required_if:is_vc_employee,' . BooleanAsInteger::TRUE,
                'string',
                'max:191'
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
                'max:191',
                Rule::unique(User::class)->ignore(request()->input('id')),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:191',
                Rule::unique(User::class)->ignore(request()->input('id')),
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
            'citizenship_id.required_if' => 'The Citizenship field is required',
            'department_id.required_if' => 'The Department field is required',
            'position_id.required_if' => 'The Position field is required',
            'account_type.required_if' => 'The Account Type field is required',
            'account_code.required_if' => 'The Account field is required',
        ];
    }
}
