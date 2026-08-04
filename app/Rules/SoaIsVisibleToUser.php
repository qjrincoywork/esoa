<?php

namespace App\Rules;

use App\Models\Soa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SoaIsVisibleToUser implements ValidationRule
{
    /**
     * Pass only when every supplied SOA id sits inside the authenticated user's
     * row-level visibility boundary.
     *
     * Reuses {@see \App\Models\Soa::scopeVisibleTo()} — the single source of truth
     * already applied to the SOA list, the export and every dashboard metric — so a
     * client-supplied id can never link a record to an invoice the user is not
     * allowed to see. This matters most for the SOA-context flows (for example the
     * concern submitted from the SOA details pane), where the id is posted by the
     * page rather than picked from a scoped dropdown.
     *
     * Accepts either a single id or an array of ids and resolves both with one
     * query, regardless of how many ids were sent.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = collect(is_array($value) ? $value : [$value])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // Fail closed: nothing usable was supplied.
        if ($ids->isEmpty()) {
            $fail('The selected billing invoice is invalid.');

            return;
        }

        $visibleCount = Soa::query()
            ->visibleTo(auth()->user())
            ->whereIn('id', $ids->all())
            ->count();

        if ($visibleCount !== $ids->count()) {
            $fail('The selected billing invoice is invalid.');
        }
    }
}
