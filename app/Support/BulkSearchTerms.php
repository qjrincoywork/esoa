<?php

namespace App\Support;

/**
 * The query side of a bulk lookup — many entries searched at once.
 *
 * A listing that offers "search multiple …" does three things with the entries, and
 * they must agree with each other: narrow the rows to those matching any entry (or the
 * one entry singled out), and count how many rows each entry matches under the
 * listing's other filters. Each listing knows what "matches" means for its own rows and
 * says so as one SQL condition per entry; this class does the rest, so every bulk
 * lookup reads its params, filters and counts the same way.
 *
 * The request side — splitting the submitted text into entries and validating them —
 * is {@see \App\Http\Requests\Concerns\AcceptsBulkSearchTerms}.
 */
final class BulkSearchTerms
{
    /**
     * The entries a listing should be narrowed by: just the one singled out when there
     * is one, otherwise all of them — empty when no bulk lookup was asked for.
     *
     * @param  array<string, mixed>  $params  Validated listing params.
     * @return array<int, string>
     */
    public static function filtering(array $params): array
    {
        if (isset($params['search_term_focus'])) {
            return [$params['search_term_focus']];
        }

        return array_values($params['search_terms'] ?? []);
    }

    /**
     * Whether entries must match exactly rather than be contained.
     *
     * @param  array<string, mixed>  $params
     */
    public static function isExact(array $params): bool
    {
        return !empty($params['exact_match']);
    }

    /**
     * The params the per-entry counts are taken under: every filter but the lookup
     * itself, so each entry is counted against the same rows the others are.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function withoutLookup(array $params): array
    {
        return array_diff_key($params, array_flip(['search_terms', 'search_term_focus']));
    }

    /**
     * Narrow a query to rows matching any of the entries.
     *
     * Grouped, so an entry's OR can never escape the listing's other filters.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $terms
     * @param  callable(string, bool): array{0: string, 1: array<int, string>}  $condition
     */
    public static function apply($query, array $terms, bool $exact, callable $condition): void
    {
        if ($terms === []) {
            return;
        }

        $query->where(function ($group) use ($terms, $exact, $condition) {
            foreach ($terms as $term) {
                $group->orWhereRaw(...$condition($term, $exact));
            }
        });
    }

    /**
     * One `SUM(CASE …)` column per entry, so every entry is counted in a single pass
     * rather than a query each. Columns are named `match_<index>`.
     *
     * SQL Server refuses to aggregate over a subquery, so the condition must not hold
     * one; a match that needs another table is joined in by the caller instead.
     *
     * @param  array<int, string>  $terms
     * @param  callable(string, bool): array{0: string, 1: array<int, string>}  $condition
     * @return array{0: string, 1: array<int, string>} The select SQL and its bindings.
     */
    public static function countColumns(array $terms, bool $exact, callable $condition): array
    {
        $columns = [];
        $bindings = [];

        foreach (array_values($terms) as $index => $term) {
            [$sql, $termBindings] = $condition($term, $exact);
            $columns[] = "SUM(CASE WHEN {$sql} THEN 1 ELSE 0 END) AS match_{$index}";
            array_push($bindings, ...$termBindings);
        }

        return [implode(', ', $columns), $bindings];
    }

    /**
     * Read the per-entry counts back off one or more count rows, in the order the
     * entries were given — several rows are summed, for a listing that spans more than
     * one table.
     *
     * @param  array<int, string>  $terms
     * @param  iterable<object|null>  $rows  Rows produced by {@see countColumns()}.
     * @return array<int, array{term: string, count: int}>
     */
    public static function matches(array $terms, iterable $rows): array
    {
        $terms = array_values($terms);
        $counts = array_fill(0, count($terms), 0);

        foreach ($rows as $row) {
            foreach ($terms as $index => $term) {
                $counts[$index] += (int) ($row->{"match_{$index}"} ?? 0);
            }
        }

        return array_map(
            static fn (string $term, int $count): array => ['term' => $term, 'count' => $count],
            $terms,
            $counts
        );
    }
}
