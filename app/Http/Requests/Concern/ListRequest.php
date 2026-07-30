<?php

namespace App\Http\Requests\Concern;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the 'concerns.index' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['concerns.index'])
        );
    }

    /**
     * Validation rules for filtering and paginating the concern listing.
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
            'description' => [
                'nullable',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
            'type' => [
                'nullable',
                'integer',
                Rule::in(ConcernType::getValues()),
            ],
            'status' => [
                'nullable',
                'integer',
                Rule::in(TicketStatus::getValues()),
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
