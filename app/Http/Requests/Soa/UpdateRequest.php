<?php

namespace App\Http\Requests\Soa;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\SoaStatus;
use App\Enums\Status;
use App\Models\Soa;
use App\Rules\IsDataExists;
use App\Rules\IsServerDataExists;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $this->merge(['user_id' => auth()->user()->id]);
        $filePdfRules = [
            'required',
            // 'required_if:status,' . SoaStatus::ENDORSED,
        ];

        if ($this->hasFile('file_pdf')) {
            $filePdfRules = [
                ...$filePdfRules,
                'file',
                'mimes:pdf',
                'max:20480', // 2MB (size is in KB)
            ];
        }

        return [
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
            ],
            'billing_ref' => [
                'required_unless:status,' . SoaStatus::ENDORSED,
                'string',
                'max:191',
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
                Rule::in(SoaStatus::getValues()),
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


    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file_pdf')) {
            return;
        }

        $existingFilePdf = Soa::whereKey($this->input('id'))->value('file_pdf');
        if (!$existingFilePdf) {
            return;
        }

        $this->merge([
            'file_pdf' => $existingFilePdf,
        ]);
    }

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
}
