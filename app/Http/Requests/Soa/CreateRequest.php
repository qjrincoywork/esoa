<?php

namespace App\Http\Requests\Soa;

use App\Enums\{ AccountType, BillRefFrom, BillType, Server };
use App\Rules\{ IsDataExists, IsServerDataExists, SoaAmountIsValid, SoaStatusIsValid };
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'user_id' => [
                'required',
                'integer',
                new IsDataExists('users'),
            ],
            'account_type' => [
                'required',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'account_code' => [
                'required',
                'string',
                'max:191',
                new IsServerDataExists(Server::HMS, 'Accounts', 'ac_code'),
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:191',
                new IsServerDataExists(Server::HMS, 'Branches', 'br_code'),
            ],
            'soa_number' => [
                'required',
                'string',
                'max:191',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('soas', 'soa_number'),
            ],
            'billing_ref_from' => [
                'nullable',
                'string',
                Rule::in(BillRefFrom::getValues()),
            ],
            'billing_ref' => [
                'nullable',
                'array',
            ],
            'billing_ref.*' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'bill_type' => [
                'required',
                'integer',
                Rule::in(BillType::getValues()),
            ],
            'due_date' => [
                'required',
                'date',
            ],
            'status' => [
                'required',
                'integer',
                new SoaStatusIsValid(),
            ],
            'period_date_from' => [
                'required',
                'date',
            ],
            'period_date_to' => [
                'required',
                'date',
            ],
            'payment_adjustment' => [
                'nullable',
                'numeric',
            ],
            'file_pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:' . config('vc.max_file_size'), // 2MB (size is in KB)
            ],
            'file_xls' => [
                'required_unless:bill_type,' . BillType::ECU,
                'file',
                'mimes:xls,xlsx',
                'max:' . config('vc.max_file_size'), // 2MB (size is in KB)
            ],
            'amount' => [
                'required',
                'numeric',
                // new SoaAmountIsValid(),
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
            'user_id.required' => 'The user field is required',
            'soa_number.regex' => 'The SOA number may only contain letters, numbers, and hyphens.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_ref' => json_decode($this->input('billing_ref'), true),
            'user_id' => auth()->user()->id,
        ]);
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'account_type' => str_starts_with($this->input('account_code'), 'TP') ? AccountType::TPA : AccountType::HMO,
        ]);
    }
}
