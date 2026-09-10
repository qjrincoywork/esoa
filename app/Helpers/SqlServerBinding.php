<?php

namespace App\Helpers;

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
     * @param  array<int, mixed>  $values
     * @return array<int, array<int, mixed>>
     */
    public static function chunkValues(array $values): array
    {
        return $values === [] ? [] : array_chunk($values, self::USABLE_PARAMETERS);
    }
}
