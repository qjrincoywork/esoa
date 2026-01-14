<?php

namespace App\Http\Requests\Soa;

use App\Enums\Server;
use App\Rules\IsServerDataExists;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class RecomputeTaxRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ref_id' => [
                'required',
                'string',
                new IsServerDataExists(Server::HMS, 'billing', 'bl_refid'),
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
            'ref_id.required' => 'The Reference ID field is required',
        ];
    }
}
