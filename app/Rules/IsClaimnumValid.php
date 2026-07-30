<?php

namespace App\Rules;

use App\Enums\Server;
use App\Helpers\SqlDatabase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsClaimnumValid implements ValidationRule
{
    /**
     * Pass only when the claim exists AND belongs to an account the authenticated
     * user is allowed to access.
     *
     * Delegates to {@see SqlDatabase::userCanAccessClaim()} on Server::HMS, which
     * resolves the claim to its cardholder/account and applies the same
     * role-based row-level filter used by the listings. This fails closed: it
     * rejects both non-existent claims and claims belonging to other tenants
     * (F-02). The previous implementation tested the truthiness of a paginator
     * object, which is always true, so it never rejected anything.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!(new SqlDatabase(Server::HMS))->userCanAccessClaim(is_string($value) ? $value : null)) {
            $fail("The claimnum is invalid.");
        }
    }
}
