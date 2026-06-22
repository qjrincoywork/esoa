<?php

namespace App\Http\Requests\AccountPayment;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'deposit_date'    => 'nullable|date',
            'mode_of_payment' => 'nullable|integer',
            'status'          => 'nullable|integer',
            'created_by'      => 'nullable|string|max:255',
            'image'           => 'nullable|string|max:255',
            'pdf'             => 'nullable|string|max:255',
            'excel'           => 'nullable|string|max:255',
            'remarks'         => 'nullable|string|max:1000',
            'per_page'        => 'nullable|integer|min:1|max:100',
            'page'            => 'nullable|integer|min:1',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->per_page ?? config('vc.default_pages', 10),
        ]);
    }
}
