<?php

namespace App\Http\Requests\Soa;

use App\Enums\BillType;
use App\Enums\Server;
use App\Enums\Status;
use App\Rules\IsDataExists;
use App\Rules\IsServerDataExists;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
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
                new IsDataExists('users'),
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
                'required',
                'string',
                'max:191',
            ],
            'billing_ref' => [
                'required',
                'string',
                'max:191',
            ],
            'bill_type' => [
                'required',
                'integer',
                Rule::in(BillType::getValues()),
            ],
            'bill_date' => [
                'required',
                'date',
            ],
            'due_date' => [
                'required',
                'date',
            ],
            'period_date_from' => [
                'required',
                'date',
            ],
            'period_date_to' => [
                'required',
                'date',
            ],
            'status' => [
                'required',
                'integer',
                Rule::in(Status::getValues()),
            ],
            'amount' => [
                'required',
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
            'file_pdf' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:20480' // 2MB (size is in KB)
            ],
            'file_xls' => [
                'nullable',
                'file',
                'mimes:xls,xlsx',
                'max:20480' // 2MB (size is in KB)
            ],
        ];
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
        ];
    }
}
