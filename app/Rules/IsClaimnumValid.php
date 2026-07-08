<?php

namespace App\Rules;

use App\Enums\Server;
use App\Helpers\SqlDatabase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsClaimnumValid implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if the value is valid
        $exists = (new SqlDatabase(Server::HMS))->getCardHolderDetailsByParams(['claimnum' => $value]);

        if (!$exists) {
            $fail("The claimnum is invalid.");
        }
    }
}
