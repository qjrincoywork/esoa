<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class FindMemberRequest extends FormRequest
{
    /**
     * Validation rules for the member search: all identity filters (policy/batch/claim
     * number, first/last name, account code, company name) are optional strings and
     * per_page is an optional integer between 1 and 100.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = config('vc.max_string_limit');

        return [
            'policynum'    => ['nullable', 'string', 'max:' . $max],
            'batch_number' => ['nullable', 'string', 'max:' . $max],
            'claimnum'     => ['nullable', 'string', 'max:' . $max],
            'lastname'     => ['nullable', 'string', 'max:' . $max],
            'firstname'    => ['nullable', 'string', 'max:' . $max],
            'account_code' => ['nullable', 'string', 'max:' . $max],
            'company_name' => ['nullable', 'string', 'max:' . $max],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Custom validation messages for the member search string and per-page rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policynum.string'    => 'Policy Number must be a string.',
            'batch_number.string' => 'Batch Number must be a string.',
            'claimnum.string'     => 'Claim Number must be a string.',
            'lastname.string'     => 'Last Name must be a string.',
            'firstname.string'    => 'First Name must be a string.',
            'account_code.string' => 'Account Code must be a string.',
            'company_name.string' => 'Company Name must be a string.',
            'per_page.integer'    => 'Per page must be an integer.',
            'per_page.min'        => 'Per page must be at least 1.',
            'per_page.max'        => 'Per page must not exceed 100.',
        ];
    }
}
