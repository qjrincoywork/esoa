<?php

namespace App\Http\Requests\Soa;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Enums\Status;
use App\Rules\IsDataExists;
use App\Rules\IsServerDataExists;
use App\Rules\SoaAmountIsValid;
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
                Rule::in(SoaStatus::getValues()),
            ],
            'period_date_from' => [
                'required',
                'date',
            ],
            'period_date_to' => [
                'required',
                'date',
            ],
            'amount' => [
                'required',
                'numeric',
                new SoaAmountIsValid(),
            ],
            'amount_paid' => [
                'nullable',
                'numeric',
            ],
            'payment_adjustment' => [
                'nullable',
                'numeric',
            ],
            'balance' => [
                'nullable',
                'numeric',
            ],
            'file_pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:20480' // 2MB (size is in KB)
            ],
            'file_xls' => [
                'nullable',
                'file',
                'mimes:xls,xlsx',
                'max:20480' // 2MB (size is in KB)
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_ref' => json_decode($this->input('billing_ref'), true),
            'user_id' => auth()->user()->id,
        ]);
    }
}
