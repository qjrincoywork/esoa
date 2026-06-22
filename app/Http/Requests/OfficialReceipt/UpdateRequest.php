<?php

namespace App\Http\Requests\OfficialReceipt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:official_receipts,id',
            ],
            'or_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('official_receipts', 'or_number')
                    ->ignore($this->integer('id'))
                    ->whereNull('deleted_at'),
            ],
            'or_date' => [
                'required',
                'date',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'account_payment_id' => [
                'nullable',
                'integer',
                'exists:account_payments,id',
            ],
            'soa_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'soa_ids.*' => [
                'integer',
                'exists:soas,id',
            ],
            'file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:' . config('vc.max_file_size'),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $soaIds = $this->input('soa_ids');
        if (is_string($soaIds)) {
            $decoded = json_decode($soaIds, true);
            if (is_array($decoded)) {
                $this->merge(['soa_ids' => $decoded]);
            }
        }
    }
}
