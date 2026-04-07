<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustAmountRequest extends FormRequest
{
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
                Rule::in(['add', 'deduct']),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    public function authorize(): bool
    {
        return auth()->check();
    }
}
