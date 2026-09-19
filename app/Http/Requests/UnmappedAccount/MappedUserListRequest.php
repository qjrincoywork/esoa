<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters accepted by the "Mapped Users" tab of one account or branch's directory pane.
 *
 * The counterpart of {@see ListRequest}: that listing answers "what has nobody got",
 * this answers "who already has this one" for a single code the reader opened.
 */
class MappedUserListRequest extends FormRequest
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
            'scope' => ['required', 'string', Rule::in(AccountDirectoryScope::getValues())],
            'code' => ['required', 'string', 'max:' . $max],
            'search' => ['nullable', 'string', 'max:' . $max],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
        ];
    }

    /**
     * Which half of the directory the code belongs to, defaulting to accounts.
     */
    public function scope(): string
    {
        return AccountDirectoryScope::resolve($this->validated('scope'));
    }

    /**
     * The account or branch code being looked up.
     */
    public function code(): string
    {
        return trim((string) $this->validated('code'));
    }

    /**
     * Everything but the fields already read through {@see scope()} and {@see code()}.
     *
     * @return array<string, mixed>
     */
    public function lookupParams(): array
    {
        return collect($this->validated())->except(['scope', 'code'])->all();
    }

    /**
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
        return ['scope.in' => 'The selected view is invalid'];
    }
}
