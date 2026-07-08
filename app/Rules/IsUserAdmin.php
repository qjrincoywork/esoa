<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsUserAdmin implements ValidationRule
{
    /**
     * Run the validation rule.
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
