<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccount extends Model
{
    protected $fillable = [
        'user_id',
        'account_type',
        'account_code',
        'branch_code',
    ];

    /**
     * Get the user this account/branch assignment belongs to (belongs-to User via user_id).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Composite identity of the account/branch pair this row maps.
     *
     * The mapping UI drags rows around by this key and dedupes on it, and the same
     * key is rebuilt client-side, so both ends agree on when two rows are the
     * "same" mapping without needing a database id for rows not yet saved.
     */
    public function getMappingKeyAttribute(): string
    {
        return self::mappingKey($this->account_code, $this->branch_code);
    }

    /**
     * Build the key identifying one account/branch mapping.
     *
     * The pair of codes is the whole identity: row-level scoping reads only
     * `account_code` and `branch_code` ({@see User::scopedAccountPairs()}), so
     * `account_type` records how the account was picked and never widens or narrows
     * what a mapping grants — two rows differing only by it would be redundant.
     *
     * A blank branch code means "every branch of this account", and collapses to the
     * same key as a null one so the two can never both be stored.
     *
     * @param  int|string|null  $accountCode
     * @param  int|string|null  $branchCode
     */
    public static function mappingKey($accountCode, $branchCode): string
    {
        return implode('|', [
            trim((string) ($accountCode ?? '')),
            trim((string) ($branchCode ?? '')),
        ]);
    }

    /**
     * Replace a user's whole set of account/branch mappings with the given rows.
     *
     * The submitted list is the complete intended state, so the existing rows are
     * dropped and the normalised set written in one batch insert — a mapping set is
     * small but can run to dozens of pairs for a group account admin, and one
     * statement beats one per row. Call inside a transaction: the delete and the
     * insert are only meaningful together.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $rows
     * @param  int|null  $limit  Rows to keep, per {@see \App\Enums\UserType::accountMappingLimit()}; null keeps all.
     * @return int Number of mappings now stored.
     */
    public static function syncForUser(User $user, iterable $rows, ?int $limit = null): int
    {
        $mappings = self::normalize($rows, $limit);

        $user->userAccounts()->delete();

        if ($mappings === []) {
            return 0;
        }

        $now = now();

        self::insert(array_map(
            static fn (array $mapping): array => $mapping + [
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $mappings
        ));

        return count($mappings);
    }

    /**
     * Normalise submitted mapping rows into insertable attributes.
     *
     * Rows without an account code are dropped (nothing to map), blank branch codes
     * collapse to null so "every branch" is stored one way only, duplicates are
     * discarded keeping the first occurrence, and the list is truncated when the
     * user's type caps how many mappings it may hold.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $rows
     * @return array<int, array{account_type: string|null, account_code: string, branch_code: string|null}>
     */
    protected static function normalize(iterable $rows, ?int $limit = null): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $row = $row instanceof Model ? $row->toArray() : (array) $row;

            $accountCode = trim((string) ($row['account_code'] ?? ''));

            if ($accountCode === '') {
                continue;
            }

            $accountType = trim((string) ($row['account_type'] ?? ''));
            $branchCode = trim((string) ($row['branch_code'] ?? ''));

            // First occurrence wins, so the account type of the row the user picked first
            // is the one kept when the same pair is submitted twice.
            $normalized[self::mappingKey($accountCode, $branchCode)] ??= [
                'account_type' => $accountType !== '' ? $accountType : null,
                'account_code' => $accountCode,
                'branch_code' => $branchCode !== '' ? $branchCode : null,
            ];
        }

        $normalized = array_values($normalized);

        return $limit === null ? $normalized : array_slice($normalized, 0, max($limit, 0));
    }
}
