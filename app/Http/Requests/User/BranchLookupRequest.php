<?php

namespace App\Http\Requests\User;

use App\Enums\AccountCodePrefix;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters accepted by the branch picker behind the user forms.
 *
 * Mirrors {@see AccountLookupRequest}: the client chooses which account's branches to
 * list and how to search them, while the excluded account classes are decided
 * server-side. A branch is excluded by the account it belongs to, so a branch of a
 * hidden account cannot be reached by searching for it directly either.
 */
class BranchLookupRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate the optional branch lookup filters: the owning account, a name to
     * search on, a code to keep visible even when it falls outside the search, and
     * paging.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'name' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'selected_code' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
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
     * The validated filters plus the exclusions the client has no say over.
     *
     * @return array<string, mixed>
     */
    public function lookupParams(): array
    {
        return $this->validated() + [
            'exclude_prefixes' => AccountCodePrefix::excludedFromUserAccess(),
        ];
    }
}
