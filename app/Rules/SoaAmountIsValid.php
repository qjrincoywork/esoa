<?php

namespace App\Rules;

use App\Enums\Server;
use App\Helpers\SqlDatabase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Metadata\RequiresOperatingSystem;

class SoaAmountIsValid implements ValidationRule
{
    /**
     * Pass only when the SOA amount equals the total balance of its billing refs.
     *
     * Reads the billing_ref array from the request, sums each ref's bl_balance
     * from the HMS server, and fails with "The total amount of the billing
     * references is not equal to the amount of the SOA." when that total does
     * not match the validated value (compared to 2 decimals). Non-array
     * billing_ref input is skipped.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $billingRefs = json_decode(request()->billing_ref, true);
        if (is_array($billingRefs)) {
            $billingRefTotal = 0;
            $billingRefBalance = (new SqlDatabase(Server::HMS))->getBillingByParams([
                'billing_ref' => $billingRefs,
            ]);
            foreach ($billingRefBalance as $billingRef) {
                $billingRefTotal += (float) $billingRef->bl_balance;
            }
            if (bccomp($billingRefTotal, (float) $value, 2) !== 0) {
                $fail('The total amount of the billing references is not equal to the amount of the SOA.');
            }
        }
    }
}
