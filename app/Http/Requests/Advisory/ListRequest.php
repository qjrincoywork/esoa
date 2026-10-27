<?php

namespace App\Http\Requests\Advisory;

use App\Enums\AdvisoryState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Authorize superadmins or users holding the 'advisories.index' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasRole('superadmin') ||
            $user->hasAnyPermission(['advisories.index'])
        );
    }

    /**
     * Validation rules for filtering and paginating the advisory listing.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'state' => [
                'nullable',
                'integer',
                Rule::in(AdvisoryState::getValues()),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:' . config('vc.default_pages'),
                'max:' . config('vc.max_per_pages'),
            ],
        ];
    }

    /**
     * Default per_page to the configured page size when it is not supplied.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->per_page ?? config('vc.default_pages', 10),
        ]);
    }
}
