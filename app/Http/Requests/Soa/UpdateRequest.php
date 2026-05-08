<?php

namespace App\Http\Requests\Soa;

use App\Enums\{ AccountType, BillType, Server, SoaStatus };
use App\Models\Soa;
use App\Rules\{ IsDataExists, IsServerDataExists, SoaStatusIsValid };
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
        $isAccountBranchAdmin = empty(auth()->user()->userDetail->employee_no);
        if ($isAccountBranchAdmin) {
            $rules = [
                'id' => [
                    'required',
                    'integer',
                    new IsDataExists('soas'),
                ],
                'user_id' => [
                    'required',
                    'integer',
                    new IsDataExists('users'),
                ],
                'status' => [
                    'required',
                    'integer',
                    new SoaStatusIsValid(),
                ],
            ];
        } else {
            $existingFilePdf = Soa::whereKey($this->input('id'))->value('file_pdf');
            $filePdfRules = [];
            if (!$existingFilePdf) {
                $filePdfRules = [
                    'required',
                    // 'required_if:status,' . SoaStatus::ENDORSED,
                ];
            }
            if ($this->hasFile('file_pdf')) {
                $filePdfRules = [
                    ...$filePdfRules,
                    'file',
                    'mimes:pdf',
                    'max:20480', // 2MB (size is in KB)
                ];
            }

            $rules = [
                'id' => [
                    'required',
                    'integer',
                    new IsDataExists('soas'),
                ],
                'user_id' => [
                    'required',
                    'integer',
                    new IsDataExists('users'),
                ],
                'account_type' => [
                    'required',
                    'string',
                    Rule::in(AccountType::getValues()),
                ],
                'account_code' => [
                    'required',
                    'string',
                    'max:191',
                    new IsServerDataExists(Server::HMS, 'Accounts', 'ac_code'),
                ],
                'branch_code' => [
                    'nullable',
                    'string',
                    'max:191',
                    new IsServerDataExists(Server::HMS, 'Branches', 'br_code'),
                ],
                'soa_number' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'string',
                    'max:191',
                    'regex:/^[A-Za-z0-9-]+$/',
                    Rule::unique('soas', 'soa_number')->ignore($this->id)
                ],
                // 'billing_ref' => [
                //     'required_unless:status,' . SoaStatus::ENDORSED,
                //     'string',
                //     // 'max:500',
                // ],
                'billing_ref' => [
                    'nullable',
                    // 'required_unless:status,' . SoaStatus::ENDORSED,
                    'array',
                ],
                'billing_ref.*' => [
                    'nullable',
                    'string',
                    'max:' . config('vc.max_string_limit'),
                ],
                'bill_type' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'integer',
                    Rule::in(BillType::getValues()),
                ],
                'due_date' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'date',
                ],
                'status' => [
                    'required',
                    'integer',
                    new SoaStatusIsValid(),
                ],
                'period_date_from' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'date',
                ],
                'period_date_to' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'date',
                ],
                'amount' => [
                    'required_unless:status,' . SoaStatus::ENDORSED,
                    'numeric',
                ],
                'amount_paid' => [
                    'nullable',
                    'numeric',
                ],
                'payment_adjustment' => [
                    'nullable',
                    'numeric',
                ],
                'balance' => [
                    'nullable',
                    'numeric',
                ],
                'file_pdf' => $filePdfRules,
                'file_xls' => [
                    'nullable',
                    'file',
                    'mimes:xls,xlsx',
                    'max:20480' // 2MB (size is in KB)
                ],
            ];
        }

        return $rules;
    }

    // protected function prepareForValidation(): void
    // {
    //     $existingFilePdf = Soa::whereKey($this->input('id'))->value('file_pdf');
    //     // if (!$existingFilePdf || !$this->hasFile('file_pdf')) {
    //     //     return;
    //     // }

    //     $this->merge([
    //         'file_pdf' => $this->hasFile('file_pdf') ? $this->file('file_pdf') : $existingFilePdf ?? null,
    //     ]);
    // }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The user field is required',
            'file_pdf.required_if' => 'The PDF file field is required',
            'file_xls.required_without' => 'The XLS file field is required',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_ref' => json_decode($this->input('billing_ref'), true),
            'user_id' => auth()->user()->id,
        ]);
    }
}
