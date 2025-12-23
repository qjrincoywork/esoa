<?php

namespace App\Http\Requests\Soa;

use App\Enums\UntagType;
use App\Rules\IsServerDataExists;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
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
                new IsServerDataExists('soa', 'Upload', 'up_id'),
            ],
            'soanum' => [
                'required',
                'string',
                new IsServerDataExists('soa', 'Upload', 'up_soanum'),
            ],
            'untag_type' => [
                'required',
                'integer',
                Rule::in(UntagType::getValues()),
            ],
            'reason' => [
                'required_if:untag_type,' . UntagType::OTHERS,
                'nullable',
                'string',
                'max:' . config('vc.default_text_limit'),
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
            'untag_type.required' => 'The untag type field is required.',
            'reason.required_if' => 'The reason field is required.',
        ];
    }
}
