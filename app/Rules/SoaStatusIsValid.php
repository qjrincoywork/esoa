<?php

namespace App\Rules;

use App\Enums\SoaStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SoaStatusIsValid implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $filteredStatusTypes = [];
        if (auth()->user()->userDetail->employee_no) {
            $filteredStatusTypes = SoaStatus::getValues()->filter(function ($status) {
                return $status != SoaStatus::ENDORSED;
            });
        } else {
            $filteredStatusTypes = SoaStatus::getValues()->filter(function ($status) {
                return $status == SoaStatus::ENDORSED;
            });
        }
        if (!in_array($value, $filteredStatusTypes)) {
            $fail('The status is invalid.');
        }
    }
}
