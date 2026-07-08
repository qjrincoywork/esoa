<?php

namespace App\Http\Requests\Soa;

use App\Enums\{ AccountType, BillRefFrom, BillType, Server };
use App\Rules\{ IsDataExists, IsServerDataExists, SoaAmountIsValid, SoaStatusIsValid };
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the "soas.store" permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.store'])
        );
    }

    /**
     * Validation rules for creating an SOA: account/branch codes must exist on the HMS
     * server, the SOA number must be unique and alphanumeric, and a PDF file (plus an
     * XLS file unless the bill type is ECU) is required alongside the billing details.
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
            'contract_date_from' => [
                'nullable',
                'date',
            ],
            'contract_date_to' => [
                'nullable',
                'date',
                'after_or_equal:contract_date_from',
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
     * Custom validation messages for the user_id requirement and SOA number format.
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

    /**
     * Decode the JSON-encoded billing_ref input into an array and set user_id to the
     * currently authenticated user before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_ref' => json_decode($this->input('billing_ref'), true),
            'user_id' => auth()->user()->id,
        ]);
    }

    /**
     * Derive the account_type from the account code prefix (TPA when it starts with "TP",
     * otherwise HMO) after validation passes.
     */
    protected function passedValidation(): void
    {
        $this->merge([
            'account_type' => str_starts_with($this->input('account_code'), 'TP') ? AccountType::TPA : AccountType::HMO,
        ]);
    }
}
