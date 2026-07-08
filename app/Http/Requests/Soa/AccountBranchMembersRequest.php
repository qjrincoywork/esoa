<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class AccountBranchMembersRequest extends FormRequest
{
    /**
     * Validation rules for the account/branch member lookup: requires an account code and
     * accepts optional branch, billing reference, period/contract date ranges, and member
     * identity filters (policy/claim number, first/last name).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = config('vc.max_string_limit');

        return [
            'account_code' => [
                'required',
                'string',
                'max:' . $max,
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:' . $max,
            ],
            'billing_ref' => [
                'nullable',
                'string',
            ],
            'period_date_from' => [
                'nullable',
                'date',
            ],
            'period_date_to' => [
                'nullable',
                'required_with:period_date_from',
                'date',
                'after_or_equal:period_date_from',
            ],
            'contract_date_from' => [
                'nullable',
                'date',
            ],
            'contract_date_to' => [
                'nullable',
                'required_with:contract_date_from',
                'date',
                'after_or_equal:contract_date_from',
            ],
            'policynum'    => ['nullable', 'string', 'max:' . $max],
            'claimnum'     => ['nullable', 'string', 'max:' . $max],
            'lastname'     => ['nullable', 'string', 'max:' . $max],
            'firstname'    => ['nullable', 'string', 'max:' . $max],
        ];
    }

    /**
     * Custom validation messages for the member identity and per-page rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policynum.string'    => 'Policy Number must be a string.',
            'claimnum.string'     => 'Claim Number must be a string.',
            'lastname.string'     => 'Last Name must be a string.',
            'firstname.string'    => 'First Name must be a string.',
            'account_code.string' => 'Account Code must be a string.',
            'per_page.integer'    => 'Per page must be an integer.',
            'per_page.min'        => 'Per page must be at least 1.',
            'per_page.max'        => 'Per page must not exceed 100.',
        ];
    }
}
