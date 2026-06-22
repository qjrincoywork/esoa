<?php

namespace App\Http\Requests\AccountPayment;

use App\Models\AccountPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApplyPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:account_payments,id',
            ],
            'applications' => [
                'required',
                'array',
                'min:1',
            ],
            'applications.*.soa_id' => [
                'required',
                'integer',
                'exists:soas,id',
            ],
            'applications.*.applied_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    /**
     * Cross-field validation: total requested must not exceed the RA's remaining budget.
     *
     * Per-SOA over-application is intentionally allowed here. When the requested amount
     * exceeds a SOA's current balance, the controller caps the actual applied amount at
     * the SOA balance and the surplus is retained as credit on the RA for future use.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $accountPayment  = AccountPayment::find($this->integer('id'));
                $remainingBudget = $accountPayment->remainingBalance();

                $totalRequested = collect($this->input('applications', []))
                    ->sum(fn ($item) => (float) ($item['applied_amount'] ?? 0));

                if ($totalRequested > $remainingBudget) {
                    $validator->errors()->add(
                        'applications',
                        sprintf(
                            'Total requested amount (%.2f) exceeds the RA\'s remaining balance (%.2f).',
                            $totalRequested,
                            $remainingBudget
                        )
                    );
                }
            },
        ];
    }
}
