<?php

namespace App\Http\Requests\Soa;

use App\Enums\AccountType;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
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
                'nullable',
                'string',
                'max:191',
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:191',
            ],
            'billing_ref' => [
                'nullable',
                'string',
                'max:191',
            ],
            'soanum' => [
                'nullable',
                'string',
                'max:191',
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in(SoaStatus::getValues()),
            ],
            'due_in' => [
                'nullable',
                'integer',
                Rule::in(SoaAging::getValues()),
            ],
            'per_page' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
