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
        $authUser = auth()->user();
        $isAccountBranchAdmin = $authUser->hasAnyRole(['account_branch_admin', 'group_account_admin']);
        if ($isAccountBranchAdmin) {
            $filteredStatusTypes = array_filter(SoaStatus::getValues(), function ($value) {
                return in_array($value, config('vc.allowed_soa_status_for_account_branch_admin'));
            });
        } else {
            $filteredStatusTypes = array_filter(SoaStatus::getValues(), function ($value) {
                return !in_array($value, config('vc.allowed_soa_status_for_account_branch_admin'));
            });
        }
        if (!in_array($value, $filteredStatusTypes)) {
            $fail('The status is invalid.');
        }
    }
}
