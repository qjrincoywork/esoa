<?php

namespace App\Http\Requests\AccountPayment;

use App\Rules\IsUserAdmin;
use Illuminate\Foundation\Http\FormRequest;

class DeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['account_payments.destroy'])
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
            'id' => [
                'required',
                'integer',
                'exists:account_payments,id',
                new IsUserAdmin()
            ]
        ];
    }
}
