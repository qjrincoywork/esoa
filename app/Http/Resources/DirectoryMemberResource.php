<?php

namespace App\Http\Resources;

use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One cardholder behind an unmapped account or branch.
 *
 * The rows the "Members" count on the listing was counting — the same set, because the
 * query behind it keys on the same single column
 * ({@see \App\Helpers\SqlDatabase::getDirectoryMembersByParams()}).
 *
 * Deliberately lean, and deliberately not the cardholder listing the SOA module uses:
 * this is a coverage-gap screen, so it shows who is sitting behind the gap, not their
 * claims history.
 */
class DirectoryMemberResource extends JsonResource
{
    /**
     * Transform the cardholder into a list row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ch_id,
            'policy_number' => $this->ch_policynum,

            // Assembled here rather than in the query, so one column sorts and exports
            // the way a reader reads a name.
            'name' => $this->fullName(),
            'last_name' => CommonHelper::convertStringEncoding(trim((string) $this->ch_lastname)),
            'first_name' => CommonHelper::convertStringEncoding(trim((string) $this->ch_firstname)),
            'middle_name' => CommonHelper::convertStringEncoding(trim((string) $this->ch_middlename)) ?: null,

            'sex' => $this->ch_sex ? strtoupper(trim((string) $this->ch_sex)) : null,
            'birth_date' => CommonHelper::formatDate($this->ch_bdate),
            'plan_code' => $this->ch_plancode,

            'effectivity_date' => CommonHelper::formatDate($this->ch_effdate),
            'expiry_date' => CommonHelper::formatDate($this->ch_expirydate),

            'account_code' => $this->ch_accountid,
            'branch_code' => $this->ch_branch_code,
            'branch_name' => CommonHelper::convertStringEncoding(trim((string) $this->ch_branch_name)) ?: null,
        ];
    }

    /**
     * The cardholder's name as HMS holds it.
     *
     * `ch_name` first, because it is the column HMS always fills — the split first/last
     * pair is empty on some 59,000 cardholders, who would otherwise be listed as blank
     * rows. The parts are assembled only as a fallback, for the reverse case.
     */
    private function fullName(): string
    {
        $stored = trim((string) $this->ch_name);

        if ($stored !== '') {
            return CommonHelper::convertStringEncoding($stored);
        }

        $given = collect([$this->ch_firstname, $this->ch_middlename, $this->ch_suffix])
            ->map(fn ($part): string => trim((string) $part))
            ->filter()
            ->implode(' ');

        $family = trim((string) $this->ch_lastname);

        $name = $family !== '' && $given !== ''
            ? "{$family}, {$given}"
            : ($family ?: $given);

        return CommonHelper::convertStringEncoding($name) ?: '—';
    }
}
