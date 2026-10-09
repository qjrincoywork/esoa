<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use App\Http\Requests\Concerns\ReadsMappingTargets;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The user picker behind "Assign users" on the unmapped listing: who could be given the
 * selected accounts/branches, a page at a time.
 *
 * Read-only, but posted rather than queried: it carries the selected rows so each user
 * comes back with their eligibility for exactly those rows, and a full page of them
 * would run a URL past the length servers accept.
 */
class AssignableUserListRequest extends FormRequest
{
    use AuthorizesRoutePermission;
    use ReadsMappingTargets;

    /**
     * Validate the selected rows, the optional search term and paging.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->targetRules(),
            'search' => ['nullable', 'string', 'max:' . config('vc.max_string_limit')],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
        ];
    }

    /**
     * The search and paging, with their defaults filled in.
     *
     * @return array{search: string, page: int, per_page: int}
     */
    public function filters(): array
    {
        return [
            'search' => trim((string) $this->validated('search', '')),
            'page' => (int) $this->validated('page', 1),
            'per_page' => (int) $this->validated('per_page', config('vc.default_pages')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'targets.required' => 'Select at least one account or branch to assign',
            'targets.max' => 'Assign at most :max accounts or branches at a time',
        ];
    }
}
