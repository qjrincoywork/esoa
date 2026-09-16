<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;

class OldRemarksRequest extends FormRequest
{
    /**
     * Allow all authenticated users; the SOA itself is authorised in the controller
     * via {@see \App\Helpers\CommonHelper::assertUserMayAccessModel()}.
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
     * Validate the SOA the conversation belongs to plus the pagination window.
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
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:' . config('vc.max_per_pages'),
            ],
        ];
    }
}
