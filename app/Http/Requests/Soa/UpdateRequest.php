<?php

namespace App\Http\Requests\Soa;

use App\Enums\UntagType;
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
        $this->merge([
            'amount_due' => str_replace(',', '', $this->amount_due),
        ]);
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
            // 'untag_type' => [
            //     'required',
            //     'integer',
            //     Rule::in(UntagType::getValues()),
            // ],
            // 'reason' => [
            //     'required_if:untag_type,' . UntagType::OTHERS,
            //     'nullable',
            //     'string',
            //     'max:' . config('vc.default_text_limit'),
            // ],
            'amount_due' => [
                'required',
                'numeric',
                // 'max:' . config('vc.default_text_limit'),
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
            'gender_id.required' => 'The Gender field is required',
            'civil_status_id.required' => 'The Civil Status field is required',
            'citizenship_id.required' => 'The Citizenship field is required',
            'department_id.required' => 'The Department field is required',
            'position_id.required' => 'The Position field is required',
        ];
    }
}
