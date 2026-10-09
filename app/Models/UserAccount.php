<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Helpers\CommonHelper;
use App\Helpers\SqlServerBinding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

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
     * Every account code that at least one user is mapped to.
     *
     * The inverse is what the unmapped-directory module lists, so "assigned" is
     * defined here once: an account counts as assigned as soon as any mapping names
     * it, whether that mapping covers the whole account or a single branch of it.
     *
     * @return array<int, string>
     */
    public static function assignedAccountCodes(): array
    {
        return self::query()
            ->whereNotNull('account_code')
            ->where('account_code', '!=', '')
            ->distinct()
            ->pluck('account_code')
            ->all();
    }

    /**
     * Every branch code named by at least one mapping.
     *
     * Mappings that cover a whole account carry no branch code and are reported by
     * {@see accountCodesMappedInFull()} instead — a branch is reachable through
     * either, so the unmapped listing has to subtract both.
     *
     * @return array<int, string>
     */
    public static function assignedBranchCodes(): array
    {
        return self::query()
            ->whereNotNull('branch_code')
            ->where('branch_code', '!=', '')
            ->distinct()
            ->pluck('branch_code')
            ->all();
    }

    /**
     * Account codes mapped without a branch, i.e. granting every branch of them.
     *
     * A blank branch code means "every branch of this account" ({@see mappingKey()}),
     * so none of those branches is unassigned even though no row names them.
     *
     * @return array<int, string>
     */
    public static function accountCodesMappedInFull(): array
    {
        return self::query()
            ->where(fn ($query) => $query->whereNull('branch_code')->orWhere('branch_code', ''))
            ->whereNotNull('account_code')
            ->where('account_code', '!=', '')
            ->distinct()
            ->pluck('account_code')
            ->all();
    }

    /**
     * Usernames mapped to each of the given account codes, matched exactly regardless
     * of branch — mirrors what makes a code count as "assigned" in
     * {@see assignedAccountCodes()}.
     *
     * Scoped to the codes asked for rather than the whole table, so a directory page of
     * a few dozen rows costs one narrow `whereIn` rather than a table-wide scan.
     *
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>> account_code => usernames
     */
    public static function usernamesByAccountCodes(array $codes): array
    {
        return self::groupUsernames(
            self::query()->whereIn('account_code', self::normalizeCodes($codes)),
            'account_code'
        );
    }

    /**
     * Usernames mapped directly to each of the given branch codes.
     *
     * Does not account for an account mapped in full — {@see usernamesByAccountCodesMappedInFull()}
     * covers that half of how a branch becomes reachable.
     *
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>> branch_code => usernames
     */
    public static function usernamesByBranchCodes(array $codes): array
    {
        return self::groupUsernames(
            self::query()->whereIn('branch_code', self::normalizeCodes($codes)),
            'branch_code'
        );
    }

    /**
     * Usernames mapped to each of the given account codes with no branch named — the
     * mappings that grant every branch of the account ({@see accountCodesMappedInFull()}).
     *
     * @param  array<int, string>  $codes
     * @return array<string, array<int, string>> account_code => usernames
     */
    public static function usernamesByAccountCodesMappedInFull(array $codes): array
    {
        return self::groupUsernames(
            self::query()
                ->where(fn ($query) => $query->whereNull('branch_code')->orWhere('branch_code', ''))
                ->whereIn('account_code', self::normalizeCodes($codes)),
            'account_code'
        );
    }

    /**
     * Every mapping row that reaches a given account code, user attached — any mapping
     * naming that account at all, whole or by one of its branches, which is the same
     * definition {@see usernamesByAccountCodes()} and {@see assignedAccountCodes()} use.
     *
     * Returns a query rather than a collection so the "Mapped Users" tab can add its
     * own search and paginate at the database rather than in memory.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function queryForAccountCode(string $accountCode)
    {
        return self::query()
            ->where('account_code', trim($accountCode))
            ->with('user:id,username,email,is_active');
    }

    /**
     * Every mapping row that reaches a given branch, user attached.
     *
     * A branch is reached two ways, so both are queried: its own code mapped directly,
     * or its account mapped in full — a blank branch code granting every branch of it
     * ({@see mappingKey()}, {@see accountCodesMappedInFull()}).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function queryForBranchCode(string $accountCode, string $branchCode)
    {
        $accountCode = trim($accountCode);
        $branchCode = trim($branchCode);

        return self::query()
            ->where(function ($query) use ($accountCode, $branchCode) {
                $query->where('branch_code', $branchCode)
                    ->orWhere(function ($inFull) use ($accountCode) {
                        $inFull->where('account_code', $accountCode)
                            ->where(fn ($q) => $q->whereNull('branch_code')->orWhere('branch_code', ''));
                    });
            })
            ->with('user:id,username,email,is_active');
    }

    /**
     * Run a mapping query, grouped by the given column, into code => usernames.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return array<string, array<int, string>>
     */
    private static function groupUsernames($query, string $groupColumn): array
    {
        return $query
            ->with('user:id,username')
            ->get([$groupColumn, 'user_id'])
            ->groupBy($groupColumn)
            ->map(fn ($rows) => $rows->pluck('user.username')->filter()->unique()->sort()->values()->all())
            ->all();
    }

    /**
     * Trim, drop blanks from, and dedupe a list of codes before it reaches a query.
     *
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    private static function normalizeCodes(array $codes): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $codes),
            static fn (string $code): bool => $code !== ''
        )));
    }

    /**
     * Replace a user's whole set of account/branch mappings with the given rows.
     *
     * The submitted list is the complete intended state, so the existing rows are
     * dropped and the normalised set written in batch inserts — a group account admin
     * can hold hundreds of pairs, and batching beats one statement per row. It is
     * batches rather than one statement because SQL Server caps a statement at
     * {@see SqlServerBinding::MAX_PARAMETERS} bound parameters, which six columns per
     * row reach at 350 mappings. Call inside a transaction: the delete and the inserts
     * are only meaningful together.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $rows
     * @param  int|null  $limit  Rows to keep, per {@see \App\Enums\UserType::accountMappingLimit()}; null keeps all.
     * @return int Number of mappings now stored.
     */
    public static function syncForUser(User $user, iterable $rows, ?int $limit = null): int
    {
        $mappings = self::normalize($rows, $limit);

        $user->userAccounts()->delete();
        self::insertFor($user, $mappings);

        return count($mappings);
    }

    /**
     * Apply a set of changes to a user's mappings, leaving the rest as stored.
     *
     * The mapping tab pages a user's mappings rather than holding them all, so it saves
     * what changed instead of the whole set ({@see syncForUser()}): the pairs removed
     * are deleted, the pairs added are written, and — when `$clearExisting` is set —
     * everything stored is dropped first. An added pair the user already keeps is
     * skipped, so a change is safe to apply twice. Rows are matched by
     * {@see mappingKey()}, so a pair stored twice is removed in full.
     *
     * Deletes go by id in batches, and inserts in batches, to stay inside SQL Server's
     * {@see SqlServerBinding::MAX_PARAMETERS}. Call inside a transaction: the deletes
     * and the inserts are only meaningful together.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $added
     * @param  array<int, string>  $removedKeys  Keys built by {@see mappingKey()}.
     * @return int Number of mappings the user holds afterwards.
     */
    public static function applyChanges(User $user, iterable $added, array $removedKeys, bool $clearExisting = false): int
    {
        $stored = $user->userAccounts()
            ->get(['id', 'account_code', 'branch_code'])
            ->groupBy(fn (self $row): string => $row->mapping_key)
            // A plain collection: Eloquent's only()/except() match model ids, not these keys.
            ->toBase();

        $dropped = $clearExisting ? $stored : $stored->only($removedKeys);
        $kept = $stored->except($dropped->keys()->all());

        foreach (SqlServerBinding::chunkValues($dropped->flatten()->pluck('id')->all()) as $ids) {
            self::query()->whereKey($ids)->delete();
        }

        $mappings = array_values(array_filter(
            self::normalize($added),
            static fn (array $mapping): bool => !$kept->has(self::mappingKey($mapping['account_code'], $mapping['branch_code']))
        ));

        self::insertFor($user, $mappings);

        return $kept->count() + count($mappings);
    }

    /**
     * The keys of every pair a user is mapped to, oldest first and without repeats.
     *
     * Codes only — nothing is labelled — so it costs one narrow query however many
     * mappings the user holds. The mapping tab keeps this whole list so it can leave
     * mapped pairs out of its pickers while showing only a page of the mappings.
     *
     * @return array<int, string>
     */
    public static function mappedKeysFor(User $user): array
    {
        return $user->userAccounts()
            ->orderBy('id')
            ->get(['account_code', 'branch_code'])
            ->map(fn (self $row): string => $row->mapping_key)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One page of a user's mappings, oldest first, optionally narrowed by a search term.
     *
     * Unfiltered, the page is cut by the database. A search matches the account and
     * branch codes and their names — and the names live in HMS, not beside the codes —
     * so a search reads the user's codes (cheap: one narrow query), resolves their
     * names in one lookup per directory, and pages the matches in memory. Either way
     * only the returned page needs labelling and badging by the caller.
     *
     * @param  array{search?: string, page?: int, per_page?: int}  $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function pageForUser(User $user, array $params)
    {
        $search = trim((string) ($params['search'] ?? ''));
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, (int) ($params['per_page'] ?? config('vc.default_pages')));
        $query = $user->userAccounts()->orderBy('id', OrderType::ASC);

        if ($search === '') {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        $rows = $query->get();
        // Labelled in the same order as the rows, so the two line up by index.
        $labelled = CommonHelper::withAccountBranchNames($rows);
        $matches = $rows->filter(fn (self $row, int $index): bool => self::rowMatches($labelled[$index], $search))->values();

        return new LengthAwarePaginator(
            $matches->forPage($page, $perPage)->values(),
            $matches->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );
    }

    /**
     * Whether a labelled mapping row contains the search term in any of its codes or
     * names, ignoring case.
     *
     * @param  array<string, mixed>  $row  A row from {@see CommonHelper::withAccountBranchNames()}.
     */
    private static function rowMatches(array $row, string $search): bool
    {
        foreach (['account_code', 'account_name', 'branch_code', 'branch_name'] as $field) {
            if (Str::contains((string) ($row[$field] ?? ''), $search, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Give the same accounts/branches to many users at once, keeping what each holds.
     *
     * The unmapped listing's "assign users" action: every pair goes to every user, and a
     * pair a user already holds is skipped for that user only, so assigning twice is
     * harmless. What the users already hold is read in one query narrowed to the pairs'
     * accounts, and the new rows are written in batches that each fit one statement.
     * Limits are the caller's to have checked ({@see \App\Enums\MappingEligibility}).
     *
     * @param  array<int, int>  $userIds
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $pairs
     * @return int Number of mappings written.
     */
    public static function grantToUsers(array $userIds, iterable $pairs): int
    {
        $mappings = self::normalize($pairs);
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($mappings === [] || $userIds === []) {
            return 0;
        }

        $held = self::heldKeysByUser($userIds, array_column($mappings, 'account_code'));
        $now = now();
        $rows = [];

        foreach ($userIds as $userId) {
            foreach ($mappings as $mapping) {
                if (isset($held[$userId][self::mappingKey($mapping['account_code'], $mapping['branch_code'])])) {
                    continue;
                }

                $rows[] = $mapping + ['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        self::insertRows($rows);

        return count($rows);
    }

    /**
     * How many mappings each user holds, and how many of the given pairs among them.
     *
     * Two narrow queries for any number of users — a count per user, and only the rows
     * on the pairs' own accounts — rather than loading every mapping of every user, which
     * for a page of group account admins runs to thousands of rows.
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, string>  $keys  Pair keys built by {@see mappingKey()}.
     * @return array<int, array{mapped: int, held: int}> Keyed by user id; every id is present.
     */
    public static function mappingSummaryFor(array $userIds, array $keys): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if ($userIds === []) {
            return [];
        }

        $keys = array_values(array_unique($keys));
        // A key is "account|branch", so its account code is everything before the bar.
        $accountCodes = array_map(static fn (string $key): string => strstr($key, '|', true) ?: $key, $keys);
        $held = self::heldKeysByUser($userIds, $accountCodes);

        $counts = self::query()
            ->selectRaw('user_id, COUNT(*) AS mapped')
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('mapped', 'user_id');

        $summary = [];

        foreach ($userIds as $userId) {
            $summary[$userId] = [
                // The SQL Server driver hands counts back as strings.
                'mapped' => (int) ($counts[$userId] ?? 0),
                'held' => count(array_intersect_key($held[$userId] ?? [], array_flip($keys))),
            ];
        }

        return $summary;
    }

    /**
     * The pair keys each user holds on the given accounts.
     *
     * @param  array<int, int>  $userIds
     * @param  array<int, string>  $accountCodes
     * @return array<int, array<string, true>> user id => key => true
     */
    private static function heldKeysByUser(array $userIds, array $accountCodes): array
    {
        $accountCodes = self::normalizeCodes($accountCodes);

        if ($userIds === [] || $accountCodes === []) {
            return [];
        }

        $held = [];

        self::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('account_code', $accountCodes)
            ->get(['user_id', 'account_code', 'branch_code'])
            ->each(function (self $row) use (&$held): void {
                $held[(int) $row->user_id][$row->mapping_key] = true;
            });

        return $held;
    }

    /**
     * Write normalised mappings for a user, in batches that each fit one statement.
     *
     * @param  array<int, array{account_type: string|null, account_code: string, branch_code: string|null}>  $mappings
     */
    private static function insertFor(User $user, array $mappings): void
    {
        $now = now();

        self::insertRows(array_map(
            static fn (array $mapping): array => $mapping + [
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $mappings
        ));
    }

    /**
     * Insert complete rows in batches that each fit inside one SQL Server statement.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function insertRows(array $rows): void
    {
        foreach (SqlServerBinding::chunkRows($rows) as $batch) {
            self::insert($batch);
        }
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
