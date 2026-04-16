<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class FileListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'soa_id' => [
                'required',
                'integer',
                'exists:soas,id',
            ],
            'billing_ref' => [
                'required',
                'string',
                'max:191',
            ],
            'claimnum' => [
                'nullable',
                'string',
                'max:191',
            ],
            'policynum' => [
                'required',
                'string',
                'max:191',
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
            'soa_id.required' => 'The SOA ID is required',
            'soa_id.integer' => 'The SOA ID must be an integer',
            'soa_id.exists' => 'The SOA ID is invalid',
            'billing_ref.required' => 'The Billing Reference is required',
            'billing_ref.string' => 'The Billing Reference must be a string',
            'billing_ref.max' => 'The Billing Reference must be less than 191 characters',
            'claimnum.string' => 'The Claim Number must be a string',
            'claimnum.max' => 'The Claim Number must be less than 191 characters',
            'policynum.required' => 'The Policy Number is required',
            'policynum.string' => 'The Policy Number must be a string',
            'policynum.max' => 'The Policy Number must be less than 191 characters',
        ];
    }
}
