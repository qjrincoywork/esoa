<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
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
            'remarks' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
        ];
    }
}
