<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillingAttachmentRequest extends FormRequest
{
    /**
     * Allow all authenticated users; the SOA (and, for a historical file, the
     * account it was filed under) is authorised in the controller via
     * {@see \App\Helpers\CommonHelper::assertUserMayAccessModel()}.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the optional activity snapshot to stream the attachment from.
     *
     * Without `activity` the SOA's current attachment is streamed; with it, the
     * attachment recorded in that activity's "from" or "to" snapshot.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'snapshot' => [
                'required_with:activity',
                'nullable',
                Rule::in(['from', 'to']),
            ],
        ];
    }
}
