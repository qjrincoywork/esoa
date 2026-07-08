<?php

namespace App\Http\Requests\Soa;

use App\Enums\AccountType;
use App\Enums\BillType;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the "soas.export" or
     * "soas.list" permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.export', 'soas.list'])
        );
    }

    /**
     * Validation rules for listing SOAs: all filters are optional (account type/code,
     * branch, billing reference, SOA number, status, bill type, aging, due-date and
     * bill-date ranges, and per-page count).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            'account_code' => [
                'nullable',
                'string',
                'max:191',
            ],
            'branch_code' => [
                'nullable',
                'string',
                'max:191',
            ],
            'billing_ref' => [
                'nullable',
                'string',
                'max:191',
            ],
            'soanum' => [
                'nullable',
                'string',
                'max:191',
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in(SoaStatus::getValues()),
            ],
            'bill_type' => [
                'nullable',
                'integer',
                Rule::in(BillType::getValues()),
            ],
            'due_in' => [
                'nullable',
                'integer',
                Rule::in(SoaAging::getValues()),
            ],
            'due_date_from' => [
                'nullable',
                'date',
            ],
            'due_date_to' => [
                'nullable',
                'date',
                'required_with:due_date_from',
                'after_or_equal:due_date_from',
            ],
            'bill_date_from' => [
                'nullable',
                'date',
            ],
            'bill_date_to' => [
                'nullable',
                'date',
                'required_with:bill_date_from',
                'after_or_equal:bill_date_from',
            ],
            'per_page' => [
                'nullable',
                'integer',
            ],
        ];
    }
}
