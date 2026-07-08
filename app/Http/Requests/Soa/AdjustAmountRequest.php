<?php

namespace App\Http\Requests\Soa;

use App\Enums\SoaAmountOperation;
use App\Helpers\CustomResponse;
use App\Rules\SoaAdjustAmountResultNotNegative;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class AdjustAmountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.adjust_amount'])
        );
    }

    public function rules(): array
    {
        return [
            'soa_id' => [
                'required',
                'integer',
                'exists:soas,id',
            ],
            'operation' => [
                'required',
                'string',
                Rule::in(SoaAmountOperation::getValues()),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                new SoaAdjustAmountResultNotNegative(),
            ],
        ];
    }

    /**
     * JSON/AJAX: match {@see CustomResponse::error} shape (same as prior controller check).
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson() || $this->ajax()) {
            throw new HttpResponseException(
                CustomResponse::error(
                    (string) ($validator->errors()->first() ?: 'Validation failed.'),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()->toArray()
                )
            );
        }

        parent::failedValidation($validator);
    }
}
