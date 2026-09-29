<?php

namespace App\Http\Requests\User;

use App\Enums\{CredentialAccess, CredentialStatus, IsActive, UserType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    /**
     * Validate the optional user listing filters: a search string, page size, a
     * user type, a department ID, an active-status flag, and the credential status and
     * access state ({@see CredentialStatus}, {@see CredentialAccess}).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search_string'     => ['nullable', 'string', 'max:' . config('vc.max_string_limit')],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:' . config('vc.max_per_pages')],
            'type'              => ['nullable', 'integer', Rule::in(UserType::getValues())],
            'department_id'     => ['nullable', 'integer', 'exists:departments,id'],
            'is_active'         => ['nullable', 'integer', Rule::in(IsActive::getValues())],
            'credential_status' => ['nullable', 'integer', Rule::in(CredentialStatus::getValues())],
            'credential_access' => ['nullable', 'integer', Rule::in(CredentialAccess::getValues())],
        ];
    }
}
