<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the envelope of a bulk user-import request.
 *
 * Only the shape of the payload is enforced here (a non-empty, bounded array of
 * row objects). Per-row business validation — required fields, uniqueness, and
 * name/branch resolution — is delegated to {@see \App\Services\UserBulkImportService}
 * so a single malformed row is reported back to the user instead of failing the
 * entire request. Authorization is handled by the superadmin route middleware.
 */
class BulkStoreRequest extends FormRequest
{
    /**
     * Only a superadmin may bulk-import users.
     *
     * This mirrors the superadmin route middleware guarding the users module and
     * enforces the boundary at the request layer as defense-in-depth: a failed
     * check yields a 403 before any row is processed.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole(config('vc.superadmin'));
    }

    /**
     * Validate that the request carries a bounded, non-empty list of row objects.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'users' => ['required', 'array', 'min:1', 'max:'.config('vc.bulk_import_max_rows')],
            'users.*' => ['array'],
        ];
    }

    /**
     * Human-readable messages for the envelope-level failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'users.required' => 'No rows were found in the uploaded file.',
            'users.array' => 'The uploaded data is not in the expected format.',
            'users.min' => 'The uploaded file must contain at least one row.',
            'users.max' => 'You can import at most :max users at a time.',
        ];
    }
}
