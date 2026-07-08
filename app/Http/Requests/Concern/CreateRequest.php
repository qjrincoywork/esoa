<?php

namespace App\Http\Requests\Concern;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the 'concerns.store' permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['concerns.store'])
        );
    }

    /**
     * Validation rules for creating a concern (linked SOAs, type, title, description, status and optional attachment).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'soa_ids' => [
                'required',
                'array',
            ],
            'soa_ids.*' => [
                'required',
                'integer',
                'exists:soas,id',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(ConcernType::getValues()),
            ],
            'title' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'description' => [
                'required',
                'string',
                'max:' . config('vc.max_text_limit'),
            ],
            'status' => [
                'required',
                Rule::in(TicketStatus::getValues()),
            ],
            'attachment' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . config('vc.max_file_size'),
            ],
        ];
    }

    /**
     * Normalize a JSON-encoded soa_ids string to an array, set user_id to the authenticated user, and force status to OPEN.
     */
    protected function prepareForValidation(): void
    {
        $soaIdsInput = $this->input('soa_ids');

        $this->merge([
            'soa_ids' => is_string($soaIdsInput)
                ? json_decode($soaIdsInput, true)
                : $soaIdsInput,
            'user_id' => auth()->id(),
            'status' => TicketStatus::OPEN,
        ]);
    }
}
