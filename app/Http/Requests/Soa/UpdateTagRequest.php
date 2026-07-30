<?php

namespace App\Http\Requests\Soa;

use App\Enums\Server;
use App\Enums\UntagType;
use App\Rules\IsServerDataExists;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    /**
     * Validate that the id and soanum exist in the SOA server Upload table, that
     * untag_type is a valid UntagType, and that a reason is provided when the untag
     * type is OTHERS.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                new IsServerDataExists(Server::SOA, 'Upload', 'up_id'),
            ],
            'soanum' => [
                'required',
                'string',
                new IsServerDataExists(Server::SOA, 'Upload', 'up_soanum'),
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
                'max:' . config('vc.max_text_limit'),
            ],
        ];
    }

    /**
     * Custom validation messages for the untag_type and conditional reason rules.
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
