<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class RecordViewedRequest extends FormRequest
{
    /**
     * Allow all authenticated users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Merge the route "id" parameter into the request payload so it can be validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    /**
     * Validate that id is a required integer referencing an existing SOA.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:soas,id',
            ],
        ];
    }
}
