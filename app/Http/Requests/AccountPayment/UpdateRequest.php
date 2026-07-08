<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the 'account_payments.update' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['account_payments.update'])
        );
    }

    /**
     * Validation rules for updating an account payment (deposit date, mode of payment, image/PDF/Excel uploads, optional SOA IDs and remarks).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:account_payments,id',
            ],
            'deposit_date' => [
                'required',
                'date',
            ],
            'mode_of_payment' => [
                'required',
                'integer',
                Rule::in(AccountPaymentMode::getValues()),
            ],
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:' . config('vc.max_file_size'),
            ],
            'pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'max:' . config('vc.max_file_size'),
            ],
            'excel' => [
                'required',
                'file',
                'mimes:xls,xlsx',
                'max:' . config('vc.max_file_size'),
            ],
            'soa_ids' => [
                'nullable',
                'array',
            ],
            'soa_ids.*' => [
                'integer',
                'exists:soas,id',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
        ];
    }

    /**
     * Decode a JSON-encoded soa_ids string into an array before validation.
     */
    protected function prepareForValidation(): void
    {
        $soaIdsInput = $this->input('soa_ids');

        if (is_string($soaIdsInput)) {
            $decodedSoaIds = json_decode($soaIdsInput, true);
            if (is_array($decodedSoaIds)) {
                $this->merge(['soa_ids' => $decodedSoaIds]);
            }
        }
    }
}
