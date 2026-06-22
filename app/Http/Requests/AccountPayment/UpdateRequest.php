<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use App\Enums\RemittanceAdviceStatus;
use App\Models\AccountPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:account_payments,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
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
                'nullable',
                'file',
                'mimes:jpg,jpeg,png',
                'max:' . config('vc.max_file_size'),
            ],
            'pdf' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:' . config('vc.max_file_size'),
            ],
            'excel' => [
                'nullable',
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
     * Clients may only edit a remittance advice while it is still Submitted.
     * Once billing picks it up the record becomes read-only via this endpoint.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $accountPayment = AccountPayment::find($this->integer('id'));

                if ((int) $accountPayment->status !== RemittanceAdviceStatus::SUBMITTED) {
                    $validator->errors()->add(
                        'id',
                        'This remittance advice can no longer be edited because it is already under review by the billing department.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $soaIdsInput = $this->input('soa_ids');

        if (is_string($soaIdsInput)) {
            $decoded = json_decode($soaIdsInput, true);
            if (is_array($decoded)) {
                $this->merge(['soa_ids' => $decoded]);
            }
        }
    }
}
