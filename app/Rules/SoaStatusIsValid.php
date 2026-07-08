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
     * Account/branch and group-account admins are limited to the statuses in
     * vc.allowed_soa_status_for_account_branch_admin; all other roles are
     * limited to the complement of that set. A value outside the permitted set
     * fails with "The status is invalid."
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
