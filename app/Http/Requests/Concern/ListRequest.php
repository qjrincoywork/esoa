<?php

namespace App\Http\Requests\Concern;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['concerns.index'])
        );
    }

    /**
     * Get the validation rules that apply to the request.
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->per_page ?? config('vc.default_pages', 10),
        ]);
    }
}
