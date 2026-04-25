<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class AccountBranchMembersRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_code' => [
                'required',
                'string',
                'max:191',
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:191',
            ],
            'billing_ref' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
        ];
    }
}
