<?php

namespace App\Rules;

use App\Enums\SoaStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SoaStatusIsValid implements ValidationRule
{
    /**
     * Pass only when the status value is one the current user may set.
     *
     * Which statuses those are is defined once, by
     * {@see SoaStatus::assignableValues()}, and read here rather than restated: the
     * forms build their options from the same call, so what a user is offered and what
     * this rule accepts cannot drift apart.
     *
     * A value outside the permitted set fails with "The status is invalid."
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Cast before comparing: a status read from a spreadsheet cell or a form field
        // arrives as the string "2", which would not match the integer 2 strictly.
        if (! is_numeric($value) || ! in_array((int) $value, SoaStatus::assignableValues(auth()->user()), true)) {
            $fail('The status is invalid.');
        }
    }
}
