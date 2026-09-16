<?php

namespace App\Http\Requests\User;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters accepted by the account picker behind the user forms.
 *
 * The whitelist matters as much as the validation: the lookup previously handed the
 * whole request to the query builder, so anything the client sent reached it. The
 * account classes that are off-limits for user access are decided here, server-side,
 * and cannot be overridden by a crafted request.
 */
class AccountLookupRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    /**
     * Validate the optional account lookup filters: a name to search on, the account
     * type, a code to keep visible even when it falls outside the search, and paging.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in(AccountType::getValues()),
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

    /**
     * Custom validation messages for the account lookup filters.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The selected account type is invalid',
        ];
    }
}
