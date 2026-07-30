<?php

namespace App\Http\Requests\Dashboard;

use App\Support\Dashboard\DashboardFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    /**
     * The dashboard is every authenticated user's landing page; what each of them may see
     * is decided by row-level scoping ({@see \App\Models\Soa::scopeVisibleTo()}) and by
     * {@see \App\Support\Dashboard\DashboardContext::canViewUserReports()}, not here.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validation rules for the dashboard slice: a named range preset or an explicit
     * date range, plus the optional user and account narrowing.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset' => [
                'nullable',
                'string',
                Rule::in(array_column(DashboardFilter::presets(), 'value')),
            ],
            'date_from' => [
                'nullable',
                'date',
                'required_with:date_to',
            ],
            'date_to' => [
                'nullable',
                'date',
                'required_with:date_from',
                'after_or_equal:date_from',
            ],
            // Soft-deleted users are not reportable, so they must not be selectable either.
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'account_code' => [
                'nullable',
                'string',
                'max:191',
            ],
        ];
    }

    /**
     * Treat blank query strings (?user_id=&preset=) as "not provided" so a cleared filter
     * falls back to its default instead of failing validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['preset', 'date_from', 'date_to', 'user_id', 'account_code'] as $key) {
            $value = $this->input($key);

            if ($value === '' || $value === 'null') {
                $normalized[$key] = null;
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
