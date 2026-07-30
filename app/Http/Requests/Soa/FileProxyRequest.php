<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class FileProxyRequest extends FormRequest
{
    /**
     * Validate that a required url string of at most 191 characters is supplied.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:191'
            ],
        ];
    }
}
