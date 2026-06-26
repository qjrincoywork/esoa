<?php

namespace App\Http\Requests\Concern;

use App\Enums\ConcernType;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['concerns.update'])
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if (auth()->user()?->hasRole('billing_admin')) {
            return [
                'id' => [
                    'required',
                    'integer',
                    'exists:concerns,id',
                ],
                'status' => [
                    'required',
                    'string',
                    Rule::in(TicketStatus::getValues()),
                ],
            ];
        } else {
            return [
                'id' => [
                    'required',
                    'integer',
                    'exists:concerns,id',
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
                    'string',
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
    }
}
