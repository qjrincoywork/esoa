<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\Gender;
use App\Enums\Server;
use App\Enums\UserImportColumn as Col;
use App\Enums\UserType;
use App\Helpers\SqlDatabase;
use App\Models\Citizenship;
use App\Models\CivilStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Orchestrates a bulk user import from parsed spreadsheet rows.
 *
 * Each row is validated and resolved independently and persisted in its own
 * transaction (best-effort), so valid rows are created even when others fail and
 * every failure is reported back with its row number and reason. Lookup tables
 * (citizenships, civil statuses, genders, roles) are primed once up-front and
 * branch→account resolutions are memoised, keeping the import query-light for
 * large files.
 */
class UserBulkImportService
{
    /** @var array<string,int> lower(name) => citizenship id */
    private array $citizenships = [];

    /** @var array<string,int> lower(name) => civil status id */
    private array $civilStatuses = [];

    /** @var list<int> valid gender values */
    private array $genders = [];

    /** @var array<string,?string> branch_code => resolved parent account code (memoised) */
    private array $branchAccountCache = [];

    /** @var array<string,true> usernames already consumed by this import */
    private array $seenUsernames = [];

    /** @var array<string,true> emails already consumed by this import */
    private array $seenEmails = [];

    private SqlDatabase $hms;

    /**
     * @param  User  $user  Prototype used to delegate user + detail persistence.
     */
    public function __construct(protected User $user)
    {
        $this->hms = new SqlDatabase(Server::HMS);
    }

    /**
     * Import the given rows and return a summary of the outcome.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array{total:int,created:int,failed:int,errors:list<array{row:int,email:?string,messages:list<string>}>}
     */
    public function import(array $rows): array
    {
        $this->primeLookups();

        $created = 0;
        $errors = [];

        foreach (array_values($rows) as $index => $rawRow) {
            $line = $index + 1; // 1-based; the header row is dropped client-side.
            $row = $this->normalizeRow(is_array($rawRow) ? $rawRow : []);

            [$rowErrors, $resolved] = $this->validateAndResolve($row, $line);

            if ($rowErrors !== null) {
                $errors[] = $rowErrors;

                continue;
            }

            try {
                $this->persistRow($resolved);
                $this->seenEmails[Str::lower($resolved['email'])] = true;
                $created++;
            } catch (\Throwable $e) {
                Log::error('UserBulkImportService: row persistence failed', [
                    'row' => $line,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = [
                    'row' => $line,
                    'email' => $row[Col::EMAIL] ?? null,
                    'messages' => ['Could not be saved due to an unexpected error.'],
                ];
            }
        }

        // Roles were assigned above; refresh Spatie's cache once for the batch.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'total' => count($rows),
            'created' => $created,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Load the name→id lookup maps once so per-row resolution is O(1).
     */
    private function primeLookups(): void
    {
        $this->citizenships = Citizenship::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Str::lower(trim((string) $name)) => $id])
            ->all();

        $this->civilStatuses = CivilStatus::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Str::lower(trim((string) $name)) => $id])
            ->all();

        $this->genders = Gender::getValues();
    }

    /**
     * Trim/normalise a raw row into a map keyed by canonical column keys.
     *
     * Header keys are lowercased and spaces collapsed to underscores so minor
     * template deviations ("First Name") still map onto the expected columns.
     * Blank cells collapse to null.
     *
     * @param  array<string,mixed>  $rawRow
     * @return array<string,?string>
     */
    private function normalizeRow(array $rawRow): array
    {
        $normalized = [];

        foreach ($rawRow as $key => $value) {
            $canonical = Str::of((string) $key)->lower()->trim()->replace(' ', '_')->value();
            $stringValue = is_scalar($value) ? trim((string) $value) : '';
            $normalized[$canonical] = $stringValue === '' ? null : $stringValue;
        }

        // Ensure every known column exists so downstream reads are total.
        foreach (Col::ordered() as $column) {
            $normalized[$column] ??= null;
        }

        return $normalized;
    }

    /**
     * Validate a single normalised row and resolve its foreign keys.
     *
     * @param  array<string,?string>  $row
     * @return array{0: ?array{row:int,email:?string,messages:list<string>}, 1: ?array<string,mixed>}
     *                                                                                                [errorEntry|null, resolvedRow|null] — exactly one is non-null.
     */
    private function validateAndResolve(array $row, int $line): array
    {
        $messages = $this->baseValidationMessages($row);

        // Duplicate detection within the same file (clearer than a DB unique hit).
        $email = $row[Col::EMAIL];
        if ($email !== null && isset($this->seenEmails[Str::lower($email)])) {
            $messages[] = "Duplicate email '{$email}' within the file.";
        }

        // Resolve lookups (only meaningful once the base value is present).
        $genderId = in_array((int) $row[Col::GENDER], $this->genders)
            ? (int) $row[Col::GENDER]
            : null;
        $civilStatusId = in_array((int) $row[Col::CIVIL_STATUS], $this->civilStatuses)
            ? (int) $row[Col::CIVIL_STATUS]
            : null;
        $citizenshipId = in_array((int) $row[Col::CITIZENSHIP], $this->citizenships)
            ? (int) $row[Col::CITIZENSHIP]
            : null;
        $type = in_array((int) $row[Col::TYPE], UserType::getValues())
            ? (int) $row[Col::TYPE]
            : null;
        $role = in_array($row[Col::ROLE], Role::pluck('name')->toArray())
            ? $row[Col::ROLE]
            : null;

        if ($row[Col::GENDER] !== null && $genderId === null) {
            $messages[] = "Unknown gender '{$row[Col::GENDER]}'.";
        }
        if ($row[Col::CIVIL_STATUS] !== null && $civilStatusId === null) {
            $messages[] = "Unknown civil status '{$row[Col::CIVIL_STATUS]}'.";
        }
        if ($row[Col::CITIZENSHIP] !== null && $citizenshipId === null) {
            $messages[] = "Unknown citizenship '{$row[Col::CITIZENSHIP]}'.";
        }
        if ($row[Col::TYPE] !== null && $type === null) {
            $messages[] = "Unknown user type '{$row[Col::TYPE]}'.";
        }

        // Role is optional but must resolve when supplied.
        if ($row[Col::ROLE] !== null) {
            if ($role === null) {
                $messages[] = "Unknown role '{$row[Col::ROLE]}'.";
            }
        }

        // Resolve the account/branch assignments. account_code values are stored
        // as-is while branch_code values are matched to their parent account;
        // either column may be comma-separated and both may be present, so one
        // user can span many accounts and/or branches (one row each).
        $accounts = $this->resolveAccounts($row, $type, $messages);

        if (! empty($messages)) {
            return [[
                'row' => $line,
                'email' => $email,
                'messages' => array_values($messages),
            ], null];
        }

        // Username is derived from the email's local part, made unique and
        // non-reserved (the sheet does not carry a username column).
        $username = $this->generateUniqueUsername((string) $email);

        return [null, [
            'username' => $username,
            'email' => $email,
            'first_name' => $row[Col::FIRST_NAME],
            'last_name' => $row[Col::LAST_NAME],
            'type' => $type,
            'gender_id' => $genderId,
            'civil_status_id' => $civilStatusId,
            'citizenship_id' => $citizenshipId,
            'role' => $role,
            'accounts' => $accounts,
        ]];
    }

    /**
     * Run the declarative field-format rules for a row and return any messages.
     *
     * @param  array<string,?string>  $row
     * @return list<string>
     */
    private function baseValidationMessages(array $row): array
    {
        $max = config('vc.max_string_limit');

        $validator = Validator::make($row, [
            Col::EMAIL => ['required', 'string', 'email', 'max:'.$max, 'unique:users,email'],
            Col::FIRST_NAME => ['required', 'string', 'max:'.$max],
            Col::LAST_NAME => ['required', 'string', 'max:'.$max],
            Col::CITIZENSHIP => ['required', 'integer', 'exists:citizenships,id'],
            Col::CIVIL_STATUS => ['required', 'integer', 'exists:civil_statuses,id'],
            Col::GENDER => ['required', 'integer', Rule::in(Gender::getValues())],
            Col::TYPE => [
                'required',
                'integer',
                Rule::in(UserType::getValues()),
            ],
            // account_code/branch_code may be comma-separated lists; each code's
            // length is validated per-entry in resolveAccounts().
            Col::ACCOUNT_CODE => ['required_without:branch_code', 'nullable', 'string'],
            Col::BRANCH_CODE => ['required_without:account_code', 'nullable', 'string'],
        ], [
            'email.email' => "Email '".($row[Col::EMAIL] ?? '')."' is not a valid email address.",
            'email.unique' => "Email '".($row[Col::EMAIL] ?? '')."' is already taken.",
        ]);

        return $validator->fails() ? array_values($validator->errors()->all()) : [];
    }

    /**
     * Resolve a branch code to its parent account code via the HMS directory,
     * memoising the (small) set of distinct branch codes in the file.
     */
    private function resolveBranchAccount(string $branchCode): ?string
    {
        $key = trim($branchCode);

        if (array_key_exists($key, $this->branchAccountCache)) {
            return $this->branchAccountCache[$key];
        }

        $branch = $this->hms->getBranch($key);
        $account = isset($branch->br_ac_code) ? trim((string) $branch->br_ac_code) : null;

        return $this->branchAccountCache[$key] = ($account !== null && $account !== '') ? $account : null;
    }

    /**
     * Resolve a row's account_code and branch_code columns into user_accounts
     * entries, processing the two columns independently.
     *
     *  - account_code: every code is stored as-is (no branch). A comma-separated
     *    value yields one entry — and therefore one user_accounts row — per code.
     *  - branch_code: every code is matched to its parent account in the HMS
     *    directory, then stored together with the branch. A comma-separated value
     *    likewise yields one entry per branch.
     *  - Both columns may be supplied at once, so a single user can hold many
     *    accounts and/or branches.
     *  - Account/Branch Admin and Group Account Admin must resolve to at least
     *    one entry, matching CreateRequest's required_if / user_accounts rules.
     *
     * Any invalid code (unknown branch or over-length) appends a message and is
     * skipped; the caller treats a non-empty message list as a failed row.
     *
     * @param  array<string,?string>  $row
     * @param  list<string>  $messages  Collected by reference.
     * @return list<array{account_code:string,branch_code:?string}>
     */
    private function resolveAccounts(array $row, ?int $type, array &$messages): array
    {
        $max = config('vc.max_string_limit');
        $entries = [];

        // account_code column: store each code verbatim, without a branch.
        if ($row[Col::ACCOUNT_CODE] !== null) {
            foreach ($this->splitList($row[Col::ACCOUNT_CODE]) as $accountCode) {
                if (Str::length($accountCode) > $max) {
                    $messages[] = "Account code '{$accountCode}' exceeds {$max} characters.";

                    continue;
                }

                $entries[] = ['account_code' => $accountCode, 'branch_code' => null];
            }
        }

        // branch_code column: resolve each branch's parent account, store both.
        if ($row[Col::BRANCH_CODE] !== null) {
            foreach ($this->splitList($row[Col::BRANCH_CODE]) as $branchCode) {
                if (Str::length($branchCode) > $max) {
                    $messages[] = "Branch code '{$branchCode}' exceeds {$max} characters.";

                    continue;
                }

                $accountCode = $this->resolveBranchAccount($branchCode);
                if ($accountCode === null) {
                    $messages[] = "Branch code '{$branchCode}' was not found.";

                    continue;
                }

                $entries[] = ['account_code' => $accountCode, 'branch_code' => $branchCode];
            }
        }

        $entries = $this->uniqueEntries($entries);

        if (empty($entries) && in_array($type, [UserType::ACCOUNT_BRANCH_ADMIN, UserType::GROUP_ACCOUNT_ADMIN], true)) {
            $messages[] = 'At least one account or branch is required for '.UserType::label((int) $type).'.';
        }

        return $entries;
    }

    /**
     * Split a comma-separated cell into a list of trimmed, unique, non-empty values.
     *
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Drop duplicate account/branch pairs so a user is never linked twice.
     *
     * @param  list<array{account_code:string,branch_code:?string}>  $entries
     * @return list<array{account_code:string,branch_code:?string}>
     */
    private function uniqueEntries(array $entries): array
    {
        $seen = [];
        $unique = [];

        foreach ($entries as $entry) {
            $key = $entry['account_code'].'|'.($entry['branch_code'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * Derive a unique, non-reserved username from an email's local part.
     *
     * The base is the substring before "@" (e.g. "jane.doe@vc.com" => "jane.doe").
     * When that base is already taken — by an existing user, a reserved word, or
     * another row in this same import — a numeric suffix is appended ("jane.doe1",
     * "jane.doe2", …) until a free username is found. The chosen name is reserved
     * for the remainder of the import so later rows cannot reuse it.
     */
    private function generateUniqueUsername(string $email): string
    {
        $base = Str::lower(trim(Str::before($email, '@')));
        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;
        while ($this->isUsernameTaken($candidate)) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        $this->seenUsernames[$candidate] = true;

        return $candidate;
    }

    /**
     * Whether a username is unavailable: already reserved in this import, a
     * reserved word, or persisted for an existing user (including trashed).
     */
    private function isUsernameTaken(string $username): bool
    {
        $key = Str::lower($username);

        if (isset($this->seenUsernames[$key])) {
            return true;
        }

        if (in_array($key, config('vc.reserved_usernames'), true)) {
            return true;
        }

        return $this->user->newQuery()->withTrashed()->where('username', $username)->exists();
    }

    /**
     * Persist a fully-resolved row: user + detail, any account(s), optional role.
     *
     * The account/branch entries are written directly (rather than through
     * User::saveUser's type-gated sync) so that every resolved account is stored
     * regardless of user type, and a single user can hold many of them.
     *
     * @param  array<string,mixed>  $resolved
     */
    private function persistRow(array $resolved): void
    {
        DB::transaction(function () use ($resolved) {
            $user = $this->user->saveUser([
                'username' => $resolved['username'],
                'email' => $resolved['email'],
                'first_name' => $resolved['first_name'],
                'last_name' => $resolved['last_name'],
                'type' => $resolved['type'],
                'gender_id' => $resolved['gender_id'],
                'civil_status_id' => $resolved['civil_status_id'],
                'citizenship_id' => $resolved['citizenship_id'],
            ]);

            if (! empty($resolved['accounts'])) {
                $user->userAccounts()->createMany(array_map(
                    fn (array $entry) => [
                        'account_type' => AccountType::TPA_HMO,
                        'account_code' => $entry['account_code'],
                        'branch_code' => $entry['branch_code'],
                    ],
                    $resolved['accounts']
                ));
            }

            if ($resolved['role'] !== null) {
                $user->assignRole($resolved['role']);
            }
        });
    }
}
