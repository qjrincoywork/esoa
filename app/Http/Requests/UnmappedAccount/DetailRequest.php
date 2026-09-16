<?php

namespace App\Http\Requests\UnmappedAccount;

use App\Enums\AccountCodePrefix;
use App\Enums\AccountDirectoryScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The one directory row a reader opened, identified by its scope and code.
 *
 * Carried as query parameters rather than path segments because the identifier is an
 * HMS code, not an id of ours: it is free text from another system, and a path segment
 * would leave its encoding to whatever the client did with it.
 */
class DetailRequest extends FormRequest
{
    /**
     * Authorize only users holding the "superadmin" role.
     *
     * Same audience as the listing that opens this, stated here rather than inherited:
     * a detail endpoint reaches any account in the directory by code, whether or not it
     * ever appeared on a listing the caller was allowed to see.
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
        return [
            'scope' => [
                'required',
                'string',
                Rule::in(AccountDirectoryScope::getValues()),
            ],
            'code' => [
                'required',
                'string',
                'max:' . config('vc.max_string_limit'),
            ],
        ];
    }

    /** Which half of the directory the code belongs to. */
    public function scope(): string
    {
        return AccountDirectoryScope::resolve($this->validated('scope'));
    }

    /** The HMS code being opened. */
    public function code(): string
    {
        return trim((string) $this->validated('code'));
    }

    /**
     * The account classes this module never shows, so a crafted code cannot reach one.
     *
     * The listing filters them out of every page ({@see ListRequest::lookupParams()});
     * without the same check here, a reader who guessed an `IN-` code could read the
     * record behind it through the detail pane.
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
