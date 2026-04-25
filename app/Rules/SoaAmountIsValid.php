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
     * Run the validation rule.
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
