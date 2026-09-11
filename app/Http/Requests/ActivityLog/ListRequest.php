<?php

namespace App\Http\Requests\ActivityLog;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     *
     * The route group already restricts this, but the trail records who changed what
     * across every audited module, so it states its own audience rather than relying
     * on where it happens to be mounted.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate the audit-trail filters.
     *
     * Module and event are constrained to the values that can actually be written, so
     * a filter can never ask for something the trail has no way of holding.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'log_name' => [
                'nullable',
                'string',
                Rule::in(AuditLogName::getValues()),
            ],
            'event' => [
                'nullable',
                'string',
                Rule::in(AuditEvent::getValues()),
            ],
            'causer_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'subject_type' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'batch_uuid' => [
                'nullable',
                'uuid',
            ],
            'date_from' => [
                'nullable',
                'date',
            ],
            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
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
            ],
        ];
    }

    /**
     * Custom validation messages for the audit-trail filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'log_name.in' => 'The selected module is not audited',
            'event.in' => 'The selected event is invalid',
            'date_to.after_or_equal' => 'The end date must not be earlier than the start date',
        ];
    }
}
