<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use App\Models\AccountPayment;
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
     * Validation rules for updating an account payment (deposit date, mode of payment, PDF upload, optional SOA IDs and remarks).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $existingFilePdf = AccountPayment::whereKey($this->input('id'))->value('pdf');
        $pdfRules = [];
        if (!$existingFilePdf) {
            $pdfRules = [
                'required',
            ];
        }
        if ($this->hasFile('pdf')) {
            $pdfRules = [
                ...$pdfRules,
                'file',
                'mimes:pdf',
                'max:' . config('vc.max_file_size'), // 2MB (size is in KB)
            ];
        }
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
            'pdf' => $pdfRules,
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
