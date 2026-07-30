<?php

namespace App\Http\Requests\Soa;

use App\Rules\IsClaimnumValid;
use Illuminate\Foundation\Http\FormRequest;

class FileListRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the "soas.file_list" permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.file_list'])
        );
    }

    /**
     * Validate the SOA id (must exist), a required policy number, and an optional
     * billing reference and claim number; billing_ref_from is required when a
     * billing_ref is supplied.
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
                'nullable',
                'string',
            ],
            'claimnum' => [
                new IsClaimnumValid(),
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'policynum' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'billing_ref_from' => [
                'required_with:billing_ref',
                'string',
            ],
        ];
    }

    /**
     * Custom validation messages for the SOA id, billing reference, claim number, and
     * policy number rules.
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
