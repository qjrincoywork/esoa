<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * The request side of a bulk lookup ("search multiple …").
 *
 * The entries arrive as one delimited string — it rides in a query string, shared with
 * exports and partial reloads — and are split here into their distinct, trimmed
 * entries, first occurrence first and compared case-insensitively, before validation.
 * The listing pages split their textarea on the same characters to preview the entries,
 * but this split is the one that counts.
 *
 * The query side is {@see \App\Support\BulkSearchTerms}.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait AcceptsBulkSearchTerms
{
    /** What separates the entries of a bulk lookup: commas, semicolons and line breaks. */
    public const SEARCH_TERM_SEPARATORS = '/[,;\r\n]+/';

    /**
     * The bulk-lookup params: the entries, whether they must match exactly, and the one
     * entry the listing is narrowed to — which must be one of them.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function bulkSearchRules(): array
    {
        return [
            'search_terms' => ['nullable', 'array', 'max:' . config('vc.max_search_terms')],
            'search_terms.*' => ['string', 'max:' . config('vc.max_string_limit')],
            'exact_match' => ['nullable', 'boolean'],
            'search_term_focus' => ['nullable', 'string', Rule::in($this->input('search_terms', []))],
        ];
    }

    /**
     * @param  string  $noun  What is being looked up, e.g. "users" — used in the limit message.
     * @return array<string, string>
     */
    protected function bulkSearchMessages(string $noun): array
    {
        return [
            'search_terms.max' => "Search for at most :max {$noun} at a time.",
            'search_term_focus.in' => 'The selected entry is not part of this search.',
        ];
    }

    /**
     * Split the submitted string into its entries. Leaves an array (or nothing) alone.
     */
    protected function splitBulkSearchTerms(): void
    {
        if (!is_string($terms = $this->input('search_terms'))) {
            return;
        }

        $this->merge([
            'search_terms' => collect(preg_split(self::SEARCH_TERM_SEPARATORS, $terms))
                ->map(fn (string $term) => trim($term))
                ->filter(fn (string $term) => $term !== '')
                ->unique(fn (string $term) => mb_strtolower($term))
                ->values()
                ->all(),
        ]);
    }
}
