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
        $isAccountBranchAdmin = empty(auth()->user()->userDetail->employee_no);
        if ($isAccountBranchAdmin) {
            $filteredStatusTypes = array_filter(SoaStatus::getValues(), function ($value) {
                return $value == SoaStatus::ENDORSED;
            });
        } else {
            $filteredStatusTypes = array_filter(SoaStatus::getValues(), function ($value) {
                return $value != SoaStatus::ENDORSED;
            });
        }
        if (!in_array($value, $filteredStatusTypes)) {
            $fail('The status is invalid.');
        }
    }
}
