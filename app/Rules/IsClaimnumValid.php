<?php

namespace App\Rules;

use App\Enums\Server;
use App\Helpers\SqlDatabase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsClaimnumValid implements ValidationRule
{
    /**
     * Pass only when the value matches a card holder on the HMS server.
     *
     * Looks the value up as a claimnum via SqlDatabase on Server::HMS and fails
     * with "The claimnum is invalid." when no matching record is found.
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
