<?php

namespace App\Http\Requests\User;

use App\Enums\UserCredentialReport;
use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Validation\Rule;

/**
 * A credential report export: which report, over the users list's own filters.
 *
 * Extends the list request so a report accepts exactly the filters the list does —
 * a filter added to the list is available to every report with no change here.
 */
class ExportCredentialReportRequest extends ListRequest
{
    use AuthorizesRoutePermission;

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report' => ['required', 'string', Rule::in(UserCredentialReport::getValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report.required' => 'Please choose a report to export.',
            'report.in' => 'The selected report does not exist.',
        ];
    }

    /**
     * The report asked for.
     */
    public function report(): string
    {
        return $this->validated('report');
    }

    /**
     * The list filters, without the report selector or paging.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return collect($this->validated())->except(['report', 'per_page'])->all();
    }
}
