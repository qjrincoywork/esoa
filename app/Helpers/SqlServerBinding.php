<?php

namespace App\Helpers;

use PDO;

/**
 * How much a single SQL Server statement may bind.
 *
 * The driver refuses any statement carrying more than 2100 bound parameters, which is
 * reached sooner than it looks: a mass insert spends one parameter per column per row,
 * and a `whereIn` spends one per value. Both are written as a single statement by
 * default, so they work fine in testing and then fail on the first genuinely large set.
 *
 * Everywhere that builds a statement whose size grows with the data goes through here,
 * so the ceiling is stated once and the batch size is derived from the shape of the
 * data rather than guessed at.
 */
final class SqlServerBinding
{
    /**
     * The documented server ceiling.
     *
     * @see https://learn.microsoft.com/en-us/sql/sql-server/maximum-capacity-specifications-for-sql-server
     */
    public const MAX_PARAMETERS = 2100;

    /**
     * Headroom held back from the documented ceiling.
     *
     * The ceiling is not reachable in practice: probing this project's connection
     * (ODBC Driver 17) found 2097 to be the largest count actually accepted, so the
     * driver keeps a few for itself. The reserve is far wider than that gap on purpose,
     * because a batched `whereIn` is often only part of its statement — anything the
     * query already binds spends from the same budget.
     */
    private const RESERVED_PARAMETERS = 100;

    /** What a statement may actually spend on data. */
    public const USABLE_PARAMETERS = self::MAX_PARAMETERS - self::RESERVED_PARAMETERS;

    /**
     * How many rows of a mass insert fit in one statement.
     *
     * Each column of each row binds one parameter, so the row budget falls as the table
     * grows columns — deriving it here means adding a column cannot silently push an
     * existing insert over the edge.
     *
     * @param  int  $columnsPerRow  Number of columns written per row.
     * @return int At least one row, so a wide table still makes progress.
     */
    public static function rowsPerStatement(int $columnsPerRow): int
    {
        return max(1, intdiv(self::USABLE_PARAMETERS, max(1, $columnsPerRow)));
    }

    /**
     * Split rows into batches that each fit inside one insert statement.
     *
     * The column count is read from the first row, so callers pass the rows they are
     * about to write and get back the statements to write them in.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<int, array<string, mixed>>> One batch per statement.
     */
    public static function chunkRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return array_chunk($rows, self::rowsPerStatement(count(reset($rows))));
    }

    /**
     * Split a list of `whereIn` values into batches that each fit in one statement.
     *
     * Only for lists that can be asked about one statement per batch. Batches combined
     * into a single statement still spend one parameter per value between them — for
     * a list that must stay inside one statement, use {@see valuesRows()}.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, array<int, mixed>>
     */
    public static function chunkValues(array $values): array
    {
        return $values === [] ? [] : array_chunk($values, self::USABLE_PARAMETERS);
    }

    /**
     * A list of any length as the rows of a `VALUES` table constructor, binding nothing.
     *
     * Some lists cannot be split across statements — an exclusion that has to hold
     * inside one paginated or UNIONed query, say — and one parameter per value runs
     * that statement into the ceiling as soon as the list grows. Written as literal
     * rows, the list costs no parameters at all, whatever its size; used as
     * `NOT EXISTS (SELECT 1 FROM (VALUES …) AS t(code) WHERE …)` it is also fast, since
     * the server plans a table of rows as a proper anti-join.
     *
     * The alternatives were measured on HMS (3,000 codes against ~4,700 branches) and
     * rejected: `STRING_SPLIT()` / `OPENJSON()` need database compatibility level 130
     * and HMS runs at 100; an XML document shredded into rows took 35–45 s, re-read for
     * every outer row; the same literals as a `NOT IN (…)` list took 4–8 s, tested one
     * by one per row; a `#temp` table does not survive the driver's prepared batches.
     * The `VALUES` rows took 0.3 s.
     *
     * Each value is quoted by the connection's own driver (`PDO::quote()`), never by
     * hand, so a value is always read as data — a quote inside one cannot end it.
     *
     * @param  array<int, string>  $values  Non-empty.
     * @return string The rows, e.g. `('A'),('B')`.
     */
    public static function valuesRows(PDO $pdo, array $values): string
    {
        return implode(',', array_map(static fn (string $value): string => '(' . $pdo->quote($value) . ')', $values));
    }
}
