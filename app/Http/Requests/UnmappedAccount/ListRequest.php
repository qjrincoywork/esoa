<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use App\Enums\AccountDirectoryView;
use App\Enums\AccountType;
use App\Enums\IsActive;
use App\Models\UserAccount;
use App\Http\Requests\Concerns\AcceptsBulkSearchTerms;
use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters accepted by the unmapped account/branch listing.
 *
 * Mirrors {@see \App\Http\Requests\User\AccountLookupRequest}: the client chooses how
 * to narrow the directory, while which account classes exist at all is decided here,
 * server-side. The whitelist matters as much as the validation — the params built by
 * {@see lookupParams()} are the only ones the query builder ever sees, so a crafted
 * request cannot reach past the exclusions or the assignment subtraction.
 */
class ListRequest extends FormRequest
{
    use AcceptsBulkSearchTerms;
    use AuthorizesRoutePermission;

    /**
     * Validate the listing filters.
     *
     * Scope, prefix and account type are constrained to what the enums define, so a
     * filter can never ask for a class of account that does not exist. The member
     * bounds are whole numbers with the upper never below the lower, which would
     * otherwise be a silently empty page rather than a stated mistake.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Which rows are listed: accounts, branches, or both together
            // ({@see AccountDirectoryView}). Still named `scope` on the wire.
            'scope' => [
                'nullable',
                'string',
                Rule::in(AccountDirectoryView::getValues()),
            ],
            // Matches a name or a code — an account's own, or for a branch its own and
            // its account's ({@see \App\Helpers\SqlDatabase::directoryTermConditions()}).
            'search_string' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            // Many names or codes at once, each matched the same way the search is.
            ...$this->bulkSearchRules(),
            // Off by default, so the listing stays the coverage gap it's named for;
            // switched on, it widens the same query to the accounts/branches someone
            // already has, so a search can confirm a code is mapped rather than missing.
            'include_mapped' => [
                'nullable',
                'boolean',
            ],
            'code_prefix' => [
                'nullable',
                'string',
                Rule::in(AccountCodePrefix::assignableToUserAccess()),
            ],
            'account_type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
            ],
            // Whether the account is still in force — and for a branch, whether the
            // account it belongs to is, which is the only status a branch has.
            'is_active' => [
                'nullable',
                'integer',
                Rule::in(IsActive::getValues()),
            ],
            'members_min' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'members_max' => [
                'nullable',
                'integer',
                'min:0',
                'gte:members_min',
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
                'max:' . config('vc.max_per_pages'),
            ],
        ];
    }

    /**
     * What is being listed — accounts, branches, or both — defaulting to both.
     */
    public function view(): string
    {
        return AccountDirectoryView::resolve($this->validated('scope'));
    }

    /**
     * The validated filters, plus everything the client has no say over: the account
     * classes that are never mappable, and the codes already mapped to someone.
     *
     * The assignment subtraction is resolved here, per kind of row the view lists, so
     * only the lists the chosen query actually reads are fetched — an account listing
     * has no use for the branch codes, and vice versa; the combined view needs both.
     * When `include_mapped` is on, the subtraction lists are left empty rather than
     * fetched: nothing needs excluding, so the query that would have built them is
     * skipped entirely.
     *
     * @return array<string, mixed>
     */
    public function lookupParams(): array
    {
        $includeMapped = $this->boolean('include_mapped');
        $view = $this->view();

        $params = $this->validated() + [
            'exclude_prefixes' => AccountCodePrefix::excludedFromUserAccess(),
            'include_mapped' => $includeMapped,
        ];

        if (AccountDirectoryView::includes($view, AccountDirectoryScope::ACCOUNT)) {
            $params += [
                'assigned_account_codes' => $includeMapped ? [] : UserAccount::assignedAccountCodes(),
            ];
        }

        if (AccountDirectoryView::includes($view, AccountDirectoryScope::BRANCH)) {
            $params += [
                'assigned_branch_codes' => $includeMapped ? [] : UserAccount::assignedBranchCodes(),
                'accounts_mapped_in_full' => $includeMapped ? [] : UserAccount::accountCodesMappedInFull(),
            ];
        }

        return $params;
    }

    /**
     * Bulk lookups arrive as one delimited string and are split into their entries
     * before validation ({@see AcceptsBulkSearchTerms}).
     */
    protected function prepareForValidation(): void
    {
        $this->splitBulkSearchTerms();
    }

    /**
     * Custom validation messages for the listing filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->bulkSearchMessages('accounts or branches') + [
            'scope.in' => 'The selected view is invalid',
            'code_prefix.in' => 'The selected account code prefix is invalid',
            'account_type.in' => 'The selected account type is invalid',
            'is_active.in' => 'The selected status is invalid',
            'members_max.gte' => 'The maximum number of members must not be lower than the minimum',
        ];
    }
}
