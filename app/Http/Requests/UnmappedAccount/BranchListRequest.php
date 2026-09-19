<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters accepted by the "Branches" tab of one account's directory pane.
 *
 * Account-only by construction: a branch has no branches of its own, so the pane never
 * shows this tab for one and no scope needs stating here.
 */
class BranchListRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $max = config('vc.max_string_limit');

        return [
            'code' => ['required', 'string', 'max:' . $max],
            'name' => ['nullable', 'string', 'max:' . $max],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
        ];
    }

    /**
     * The account code whose branches are being listed.
     */
    public function code(): string
    {
        return trim((string) $this->validated('code'));
    }

    /**
     * Everything but the code the query is already keyed on.
     *
     * @return array<string, mixed>
     */
    public function lookupParams(): array
    {
        return collect($this->validated())->except(['code'])->all();
    }

    /**
     * @return array<int, string>
     */
    public function excludedPrefixes(): array
    {
        return AccountCodePrefix::excludedFromUserAccess();
    }
}
