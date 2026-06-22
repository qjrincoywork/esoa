<?php

namespace App\Http\Requests\OfficialReceipt;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'or_number'          => 'nullable|string|max:255',
            'or_date'            => 'nullable|date',
            'soa_id'             => 'nullable|integer|exists:soas,id',
            'account_payment_id' => 'nullable|integer|exists:account_payments,id',
            'per_page'           => 'nullable|integer|min:1|max:100',
            'page'               => 'nullable|integer|min:1',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->per_page ?? config('vc.default_pages', 10),
        ]);
    }
}
