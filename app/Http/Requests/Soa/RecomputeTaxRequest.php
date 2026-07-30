<?php

namespace App\Http\Requests\Soa;

use App\Enums\Server;
use App\Rules\IsServerDataExists;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class RecomputeTaxRequest extends FormRequest
{
    /**
     * Validate a required ref_id string that must exist as bl_refid in the HMS billing table.
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
     * Custom validation messages for the ref_id rules.
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
