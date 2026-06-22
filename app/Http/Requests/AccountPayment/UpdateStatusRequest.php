<?php

namespace App\Http\Requests\AccountPayment;

use App\Enums\RemittanceAdviceStatus;
use App\Models\AccountPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:account_payments,id',
            ],
            'status' => [
                'required',
                'integer',
                Rule::in(RemittanceAdviceStatus::getValues()),
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
        ];
    }

    /**
     * Enforce valid status transitions after field-level validation passes.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $accountPayment = AccountPayment::find($this->integer('id'));
                $currentStatus  = (int) $accountPayment->status;
                $nextStatus     = $this->integer('status');

                if (!RemittanceAdviceStatus::canTransition($currentStatus, $nextStatus)) {
                    $validator->errors()->add(
                        'status',
                        sprintf(
                            'Cannot transition from "%s" to "%s".',
                            RemittanceAdviceStatus::label($currentStatus),
                            RemittanceAdviceStatus::label($nextStatus)
                        )
                    );
                }
            },
        ];
    }
}
