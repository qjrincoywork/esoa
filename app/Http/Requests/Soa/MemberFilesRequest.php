<?php

namespace App\Http\Requests\Soa;

use App\Rules\IsClaimnumValid;
use Illuminate\Foundation\Http\FormRequest;

class MemberFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.member_files'])
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claimnum' => [
                new IsClaimnumValid(),
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
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
            'claimnum.required' => 'The Claim Number is required.',
            'claimnum.string'   => 'The Claim Number must be a string.',
            'claimnum.max'      => 'The Claim Number must not exceed 191 characters.',
        ];
    }
}
