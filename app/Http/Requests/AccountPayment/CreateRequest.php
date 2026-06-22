<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use App\Enums\RemittanceAdviceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
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

    protected function prepareForValidation(): void
    {
        $soaIdsInput = $this->input('soa_ids');

        if (is_string($soaIdsInput)) {
            $decoded = json_decode($soaIdsInput, true);
            if (is_array($decoded)) {
                $this->merge(['soa_ids' => $decoded]);
            }
        }

        $this->merge([
            'user_id' => Auth::id(),
            // Status is always Submitted on creation — set by the system, not the client.
            'status'  => RemittanceAdviceStatus::SUBMITTED,
        ]);
    }
}
