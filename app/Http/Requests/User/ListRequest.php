<?php

namespace App\Http\Requests\User;

use App\Enums\{CredentialAccess, CredentialStatus, IsActive, UserType};
use App\Http\Requests\Concerns\AcceptsBulkSearchTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    use AcceptsBulkSearchTerms;

    /**
     * Validate the optional user listing filters: a search string, a bulk lookup (many
     * usernames/emails at once, optionally exact, optionally narrowed to one of them —
     * {@see AcceptsBulkSearchTerms}), page size, a user type, a department ID, an
     * active-status flag, and the credential status and access state
     * ({@see CredentialStatus}, {@see CredentialAccess}).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string'     => ['nullable', 'string', 'max:' . config('vc.max_string_limit')],
            ...$this->bulkSearchRules(),
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
            'type'              => ['nullable', 'integer', Rule::in(UserType::getValues())],
            'department_id'     => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'         => ['nullable', 'integer', Rule::in(IsActive::getValues())],
            'credential_status' => ['nullable', 'integer', Rule::in(CredentialStatus::getValues())],
            'credential_access' => ['nullable', 'integer', Rule::in(CredentialAccess::getValues())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->bulkSearchMessages('users');
    }

    /**
     * A bulk lookup arrives as one delimited string — it rides in a query string, shared
     * with the report export — and is split into its entries before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->splitBulkSearchTerms();
    }
}
