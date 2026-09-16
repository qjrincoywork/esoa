<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The members behind one unmapped account or branch.
 *
 * Takes the same scope and code as {@see DetailRequest}, plus paging and the two
 * fields a reader searches a member list by. The scope decides which `cholders` column
 * the lookup keys on, which is what keeps the list and the count on the listing talking
 * about the same set of people.
 */
class MemberListRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     *
     * This lists cardholders — people — for any account in the directory, so it states
     * its audience rather than relying on the route group it happens to sit in.
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
            'scope' => [
                'required',
                'string',
                Rule::in(AccountDirectoryScope::getValues()),
            ],
            'code' => [
                'required',
                'string',
                'max:' . $max,
            ],
            'name' => ['nullable', 'string', 'max:' . $max],
            'policynum' => ['nullable', 'string', 'max:' . $max],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
        ];
    }

    /** Which half of the directory the code belongs to. */
    public function scope(): string
    {
        return AccountDirectoryScope::resolve($this->validated('scope'));
    }

    /** The HMS code whose members are being listed. */
    public function code(): string
    {
        return trim((string) $this->validated('code'));
    }

    /**
     * The `cholders` column this scope counts members by.
     *
     * The same column {@see \App\Helpers\SqlDatabase::attachAccountMemberCounts()} and
     * {@see \App\Helpers\SqlDatabase::attachBranchMemberCounts()} group by, so the list
     * a reader opens holds exactly the rows the number on the listing counted.
     */
    public function memberColumn(): string
    {
        return $this->scope() === AccountDirectoryScope::BRANCH ? 'ch_branch_code' : 'ch_accountid';
    }

    /**
     * The validated search and paging filters, without the row identity.
     *
     * @return array<string, mixed>
     */
    public function lookupParams(): array
    {
        return collect($this->validated())->except(['scope', 'code'])->all();
    }

    /**
     * The account classes this module never shows.
     *
     * @return array<int, string>
     */
    public function excludedPrefixes(): array
    {
        return AccountCodePrefix::excludedFromUserAccess();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.in' => 'The selected view is invalid',
        ];
    }
}
