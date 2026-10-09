<?php

namespace App\Http\Requests\User;

use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for one page of a user's saved account/branch mappings (the mapping tab's
 * "Mapped" panel).
 *
 * The panel is paged because labelling and badging a mapping costs HMS lookups, and a
 * group account admin can hold hundreds of them — only what is on screen is labelled.
 */
class MappedAccountListRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    /**
     * Rows per page when the client does not ask for a size: a screenful of the panel.
     */
    public const DEFAULT_PER_PAGE = 20;

    /**
     * Validate the optional search term and paging.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
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
                'max:' . config('vc.max_per_pages'),
            ],
        ];
    }

    /**
     * The validated filters with their defaults filled in, ready for
     * {@see \App\Models\UserAccount::pageForUser()}.
     *
     * @return array{search: string, page: int, per_page: int}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE),
        ];
    }
}
