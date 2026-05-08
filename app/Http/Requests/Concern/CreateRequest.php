<?php

namespace App\Http\Requests\Concern;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                'exists:users,id',
            ],
            'billing_invoice' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(ConcernType::getValues()),
            ],
            'title' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'description' => [
                'required',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
            'status' => [
                'required',
                Rule::in(TicketStatus::getValues()),
            ],
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . config('vc.max_file_size'),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => auth()->id(),
            'status' => TicketStatus::OPEN,
        ]);
    }
}
