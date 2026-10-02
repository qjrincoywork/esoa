<?php

namespace App\Http\Requests\User;

use App\Enums\{CredentialAccess, CredentialStatus, IsActive, UserType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * What separates the entries of a bulk lookup: commas, semicolons and line breaks.
     * The list page splits its textarea on the same characters to preview the entries,
     * but this split is the one that counts.
     */
    public const SEARCH_TERM_SEPARATORS = '/[,;\r\n]+/';

    /**
     * Validate the optional user listing filters: a search string, a bulk lookup (many
     * usernames/emails at once, optionally exact, optionally narrowed to one of them),
     * page size, a user type, a department ID, an active-status flag, and the credential
     * status and access state ({@see CredentialStatus}, {@see CredentialAccess}).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string'     => ['nullable', 'string', 'max:' . config('vc.max_string_limit')],
            'search_terms'      => ['nullable', 'array', 'max:' . config('vc.max_search_terms')],
            'search_terms.*'    => ['string', 'max:' . config('vc.max_string_limit')],
            'exact_match'       => ['nullable', 'boolean'],
            'search_term_focus' => ['nullable', 'string', Rule::in($this->input('search_terms', []))],
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
        return [
            'search_terms.max' => 'Search for at most :max users at a time.',
            'search_term_focus.in' => 'The selected entry is not part of this search.',
        ];
    }

    /**
     * A bulk lookup arrives as one delimited string — it rides in a query string, shared
     * with the report export — and is split here into its distinct, trimmed entries,
     * first occurrence first and compared case-insensitively, before validation.
     */
    protected function prepareForValidation(): void
    {
        if (!is_string($terms = $this->input('search_terms'))) {
            return;
        }

        $this->merge([
            'search_terms' => collect(preg_split(self::SEARCH_TERM_SEPARATORS, $terms))
                ->map(fn (string $term) => trim($term))
                ->filter(fn (string $term) => $term !== '')
                ->unique(fn (string $term) => mb_strtolower($term))
                ->values()
                ->all(),
        ]);
    }
}
