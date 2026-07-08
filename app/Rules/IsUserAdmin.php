<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsUserAdmin implements ValidationRule
{
    /**
     * Pass only when the authenticated user is privileged.
     *
     * The value itself is ignored; the rule fails with "User is restricted."
     * unless the current user holds the configured superadmin role or "admin".
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Align with the request-level authorize() checks, which treat both
        // superadmin and admin as privileged (see e.g. Soa\DestroyRequest).
        $authUser = auth()->user();
        if (!$authUser || !$authUser->hasAnyRole([config('vc.superadmin'), 'admin'])) {
            $fail('User is restricted.');
        }
    }
}
