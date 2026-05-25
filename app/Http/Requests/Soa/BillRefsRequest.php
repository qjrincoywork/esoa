<?php

namespace App\Http\Requests\Soa;

use App\Enums\{ AccountType, BillRefFrom };
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillRefsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'account_code' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'billing_ref_from' => [
                'required',
                'integer',
                Rule::in(BillRefFrom::getValues()), // Assuming these are the only valid values based on BillRefFrom enum
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'name' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'billing_date_from' => [
                'nullable',
                'date',
            ],
            'billing_date_to' => [
                'nullable',
                'required_with:billing_date_from',
                'date',
                'after_or_equal:billing_date_from',
            ],
            // 'billing_refs' => [
            //     'nullable',
            //     'array',
            // ],
            'billing_refs' => [
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'per_page' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
