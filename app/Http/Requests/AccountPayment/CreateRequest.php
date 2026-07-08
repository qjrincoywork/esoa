<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\AccountPaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['account_payments.create'])
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
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
     * Prepare the data for validation.
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

        $this->merge([
            'user_id' => auth()->id(),
        ]);
    }
}
