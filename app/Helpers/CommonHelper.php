<?php

namespace App\Helpers;

use App\Enums\Server;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use App\Helpers\SqlDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CommonHelper
{
    /**
     * HMS account names resolved this request, keyed by account code.
     * An empty value means the code was looked up and HMS has no name for it.
     *
     * @var array<string, string>
     */
    protected static array $accountNameCache = [];

    /**
     * HMS branch names resolved this request, keyed by branch code.
     * An empty value means the code was looked up and HMS has no name for it.
     *
     * @var array<string, string>
     */
    protected static array $branchNameCache = [];

    /**
     * HMS system-user display names resolved this request, keyed by login.
     * An empty value means the login was looked up and HMS has no user for it.
     *
     * @var array<string, string>
     */
    protected static array $systemUserNameCache = [];

    /**
     * Best-effort fix for legacy mojibake like:
     * - "PIÃ‘AS" or "PIÃƒâ€˜AS" -> "PIÑAS"
     *
     * NOTE: This is a heuristic. The real fix is to store text as proper UTF-8.
     */
    public static function convertStringEncoding($string)
    {
        if ($string === null) {
            return null;
        }

        // Case 1: String is valid UTF-8 but clearly mojibake (Ã… sequences, etc.)
        if (mb_check_encoding($string, 'UTF-8') && str_contains($string, 'Ã')) {
            // Common repair: interpret current UTF-8 string as ISO-8859-1 and
            // re-encode to UTF-8 once. This often turns "PIÃ‘AS" into "PIÑAS".
            $fixed = utf8_encode(utf8_decode($string));

            // Fix very common ñ/Ñ mojibake variants explicitly
            $fixed = strtr($fixed, [
                'Ã‘' => 'Ñ',
                'Ã±' => 'ñ',
                'Ã?' => 'Ñ',
            ]);

            return $fixed;
        }

        // Case 2: Not valid UTF-8 at all; try Latin1 -> UTF-8 conversion once.
        if (!mb_check_encoding($string, 'UTF-8')) {
            $fixed = @mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');

            if (mb_check_encoding($fixed, 'UTF-8')) {
                $fixed = strtr($fixed, [
                    'Ã‘' => 'Ñ',
                    'Ã±' => 'ñ',
                    'Ã?' => 'Ñ',
                ]);

                return $fixed;
            }

            return $string;
        }

        // Already valid and not obviously mojibake; return as-is.
        return $string;
    }

    /**
     * Format a date string according to the given parameters.
     *
     * @param  string|null  $date
     * @param  bool  $withTime
     * @return  string|null
     */
    public static function formatDate($date, $withTime = false)
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date);

        return $withTime
            ? $date->format('F j, Y h:i A')
            : $date->format('F j, Y');
    }

    /**
     * Format a numeric amount as peso currency (e.g. "₱1,234.56").
     *
     * Single place where the currency sign and precision are decided, so exports,
     * resources and dashboard widgets render money identically.
     *
     * @param  float|int|string|null  $amount
     * @param  int  $decimals
     * @return string
     */
    public static function formatMoney($amount, int $decimals = 2): string
    {
        return config('vc.peso_sign') . number_format((float) ($amount ?? 0), $decimals);
    }

    /**
     * Get filtered original values, excluding ignored keys.
     *
     * @param  object  $model
     * @return array
     */
    public static function getFilteredOriginal($model): array
    {
        return collect($model->getOriginal())
            ->filter(function ($value, $key) {
                return !in_array($key, config('vc.ignored_diff_keys', []));
            })
            ->toArray();
    }

    /**
     * Get filtered changed values, excluding ignored keys.
     *
     * @param  object  $model
     * @return array
     */
    public static function getFilteredChanges($model): array
    {
        return collect($model->getChanges())
            ->filter(function ($value, $key) {
                return !in_array($key, config('vc.ignored_diff_keys', []));
            })
            ->toArray();
    }

    /**
     * Check if a model has account code and request has file uploaded.
     *
     * @param  object  $model
     * @param  object  $request
     * @param  string  $fileField
     * @return bool
     */
    public static function hasFileAttachmentAndAccount($model, $request, string $fileField = 'file_pdf'): bool
    {
        return $request->hasFile($fileField) && !empty($model->account_code);
    }

    /**
     * Store uploaded files with SOA number in filename.
     *
     * @param  string  $soaNumber
     * @param  string  $accountCode
     * @param  string|null  $branchCode
     * @param  object  $request
     * @param  array  $validated
     * @param  array  $fileTypes
     * @return void
     */
    public static function storeUploadedFiles(
        string $soaNumber,
        string $accountCode,
        ?string $branchCode,
        $request,
        array &$validated,
        array $fileTypes = ['file_pdf', 'file_xls']
    ): void {
        $directory = $accountCode . (!empty($branchCode) ? "/" . $branchCode : "");

        foreach ($fileTypes as $fileType) {
            if ($request->hasFile($fileType)) {
                $file = $request->file($fileType);
                $filename = $soaNumber . '_' . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
                $validated[$fileType] = $file->storeAs($directory, $filename, config('vc.disks.billing'));
            }
        }
    }

    /**
     * Store uploaded file(s) and populate the validated payload with stored paths.
     *
     * $fileField may be a string (single field) or an array of field names.
     * If a single field contains multiple files (array), the corresponding
     * validated value will be an array of stored paths.
     */
    public static function storeUploadedFile(
        $request,
        array &$validated,
        $fileField,
        $directory = null,
        $model = null,
        $disk = null,
    ): void {
        $username = auth()->user()?->username ?? 'unknown_user';

        if (is_string($directory) && $directory !== '') {
            $finalDir = $directory;
        } elseif (!empty($model)) {
            $finalDir = $username . '/' . $model->id;
        } else {
            $finalDir = $username;
        }

        $disk = $disk ?? config('vc.disks.concerns');

        $fields = is_array($fileField) ? $fileField : [$fileField];

        foreach ($fields as $field) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $files = $request->file($field);

            // Multiple files under the same field
            if (is_array($files) || $files instanceof \Illuminate\Support\Collection) {
                $stored = [];
                foreach ($files as $index => $file) {
                    if (!$file) {
                        continue;
                    }
                    $preString = !empty($model) ? $model->id . '_' : '';
                    $filename = $preString . now()->format('Ymd_His') . '_' . $index . '.' . $file->getClientOriginalExtension();
                    $stored[] = $file->storeAs($finalDir, $filename, $disk);
                }
                $validated[$field] = $stored;
                continue;
            }

            // Single file
            $file = $files;
            if ($file) {
                $preString = !empty($model) ? $model->id . '_' : '';
                $filename = $preString . now()->format('Ymd_His') . '.' . $file->getClientOriginalExtension();
                $validated[$field] = $file->storeAs($finalDir, $filename, $disk);
            }
        }
    }

    /**
     * Set client name from account or branch data.
     *
     * @param  object  $model
     * @return void
     */
    public static function setClientName($model): void
    {
        $account = (new SqlDatabase(Server::HMS))->getAccount($model->account_code);
        $model->client_name = $account->ac_name ?? $model->account_code;

        if (!empty($model->branch_code)) {
            $branch = (new SqlDatabase(Server::HMS))->getBranch($model->branch_code);
            $model->client_name = $branch?->br_branch_name ?? $model->branch_code;
        }
    }

    /**
     * Attach account/branch display names to rows that only carry codes.
     *
     * `user_accounts` stores codes only, so a saved assignment would render as a raw
     * code where the account/branch pickers show a name. Codes are resolved in bulk —
     * two HMS lookups for the whole set, however many rows — and labelled exactly the
     * way {@see \App\Http\Resources\AccountResource} and
     * {@see \App\Http\Resources\BranchResource} label picker options, so a saved row
     * reads identically to one that was just picked. Codes HMS no longer knows fall
     * back to the code itself.
     *
     * Resolved names are memoised for the request, so priming this once with every row
     * on a page keeps later per-row calls query-free.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $rows
     * @return array<int, array<string, mixed>> The rows as arrays, plus account_name / branch_name.
     */
    public static function withAccountBranchNames(iterable $rows): array
    {
        $rows = collect($rows)
            ->map(fn ($row) => $row instanceof Model ? $row->toArray() : (array) $row)
            ->all();

        if ($rows === []) {
            return [];
        }

        self::cacheAccountBranchNames(
            array_column($rows, 'account_code'),
            array_column($rows, 'branch_code')
        );

        return array_map(function (array $row) {
            $accountCode = $row['account_code'] ?? null;
            $branchCode = $row['branch_code'] ?? null;
            $accountName = $accountCode ? (self::$accountNameCache[$accountCode] ?? null) : null;
            $branchName = $branchCode ? (self::$branchNameCache[$branchCode] ?? null) : null;

            return $row + [
                'account_name' => $accountName ?: $accountCode,
                'branch_name' => $branchName ?: $branchCode,
            ];
        }, $rows);
    }

    /**
     * Warm the name memo for a whole set of rows at once.
     *
     * Call this before labelling rows one group at a time — a page of users, say — so
     * the per-group calls resolve from memory instead of querying HMS per group.
     *
     * @param  iterable<int, array<string, mixed>|\Illuminate\Database\Eloquent\Model|object>  $rows
     * @return void
     */
    public static function primeAccountBranchNames(iterable $rows): void
    {
        self::withAccountBranchNames($rows);
    }

    /**
     * Resolve any account/branch codes not yet memoised, in one lookup per directory.
     *
     * Unresolvable codes are cached as an empty string so a missing record is not
     * looked up again for the rest of the request.
     *
     * @param  array<int, string|null>  $accountCodes
     * @param  array<int, string|null>  $branchCodes
     * @return void
     */
    protected static function cacheAccountBranchNames(array $accountCodes, array $branchCodes): void
    {
        $accountCodes = self::uncachedKeys($accountCodes, self::$accountNameCache);
        $branchCodes = self::uncachedKeys($branchCodes, self::$branchNameCache);

        if ($accountCodes === [] && $branchCodes === []) {
            return;
        }

        $sqlDatabase = new SqlDatabase(Server::HMS);

        if ($accountCodes !== []) {
            $names = $sqlDatabase->getAccountNamesByCodes($accountCodes);

            foreach ($accountCodes as $code) {
                self::$accountNameCache[$code] = self::convertStringEncoding($names[$code] ?? '');
            }
        }

        if ($branchCodes !== []) {
            $names = $sqlDatabase->getBranchNamesByCodes($branchCodes);

            foreach ($branchCodes as $code) {
                self::$branchNameCache[$code] = self::convertStringEncoding($names[$code] ?? '');
            }
        }
    }

    /**
     * Resolve an HMS system user's display name from their login.
     *
     * Returns null when the login is blank or HMS has no such user — legacy records
     * reference long-deactivated accounts, and those keep whatever name the record
     * itself carries.
     *
     * @param  string|null  $login
     * @return string|null
     */
    public static function systemUserName(?string $login): ?string
    {
        $login = trim((string) $login);

        if ($login === '') {
            return null;
        }

        if (! array_key_exists($login, self::$systemUserNameCache)) {
            self::primeSystemUserNames([$login]);
        }

        return self::$systemUserNameCache[$login] ?: null;
    }

    /**
     * Resolve every not-yet-memoised login in a single HMS lookup.
     *
     * Call this with a whole page of logins before reading them one at a time through
     * {@see systemUserName()}, which then costs no queries at all.
     *
     * @param  iterable<int, string|null>  $logins
     * @return void
     */
    public static function primeSystemUserNames(iterable $logins): void
    {
        $logins = self::uncachedKeys(
            collect($logins)->map(fn ($login) => trim((string) $login))->all(),
            self::$systemUserNameCache
        );

        if ($logins === []) {
            return;
        }

        // SQL Server matches logins case-insensitively but returns them as stored, so
        // re-key the result to the casing we asked with before reading it back.
        $names = collect((new SqlDatabase(Server::HMS))->getSystemUserNamesByLogins($logins))
            ->keyBy(fn ($name, $login) => strtoupper((string) $login));

        foreach ($logins as $login) {
            self::$systemUserNameCache[$login] = self::convertStringEncoding(
                $names[strtoupper($login)] ?? ''
            );
        }
    }

    /**
     * Reduce a set of lookup keys to the distinct, non-empty ones not memoised yet.
     *
     * @param  array<int, string|null>  $keys
     * @param  array<string, string>  $cache
     * @return array<int, string>
     */
    protected static function uncachedKeys(array $keys, array $cache): array
    {
        return array_values(array_diff(
            array_unique(array_filter($keys)),
            array_keys($cache)
        ));
    }

    /**
     * Send billing invoice email and record activity.
     *
     * @param  object  $model
     * @param  object  $user
     * @param  string  $mailClass
     * @return void
     */
    public static function sendBillingInvoiceEmail($model, $user, string $mailClass): void
    {
        self::setClientName($model);
        $model->contact = config('vc.contact_email');

        $isAccountBranchAdmin = $user->hasAnyRole(['account_branch_admin', 'group_account_admin']);
        $billingNotificationEmail = config('vc.billing_notification_email', 'billing@example.com');

        $toEmail = $isAccountBranchAdmin
            ? $billingNotificationEmail
            : $user->email;

        $ccEmail = $isAccountBranchAdmin
            ? $user->email
            : $billingNotificationEmail;

        Mail::to($toEmail)
            ->cc($ccEmail)
            ->send(new $mailClass($model));

        $model->recordActivity('billing_invoice_email_sent', [
            'to' => [
                'soa_number' => $model->soa_number,
                'file_pdf' => $model->file_pdf,
                'notified_email' => $toEmail,
            ],
        ], $user);
    }

    /**
     * Send a generic notification email to the configured contact address and CC the request creator.
     *
     * @param  object  $model
     * @param  object  $user
     * @param  string  $mailClass
     * @param  string|null  $toEmail
     * @return void
     */
    public static function sendNotificationEmail($model, $user, string $mailClass, ?string $toEmail = null): void
    {
        $toEmail = $toEmail ?? config('vc.contact_email');

        if (empty($toEmail) || empty($user?->email)) {
            return;
        }

        Mail::to($toEmail)
            ->cc($user->email)
            ->send(new $mailClass($model));
    }

    /**
     * Validate if a resource is in paid status.
     *
     * @param  object  $model
     * @param  mixed  $paidStatus
     * @throws \Exception
     * @return void
     */
    public static function validateNotPaid($model, $paidStatus): void
    {
        if ($model->status === $paidStatus) {
            throw new \Exception('Record has already been paid.');
        }
    }

    /**
     * Authorize the authenticated user against a specific model instance.
     *
     * Full-access staff roles (superadmin/admin/billing_admin) pass
     * unconditionally. Every other (tenant-scoped) role must OWN the supplied
     * model, so object routes must pass a model — the check fails closed (403)
     * when none is given. Ownership is resolved by the model's shape: records
     * that carry an account_code (e.g. SOA) are matched against the
     * account(s)/branch(es) the user is assigned to (see {@see userOwnsAccount()});
     * records that carry a user_id (e.g. concerns, account payments) are matched
     * against the owner id. All other cases abort: 401 when unauthenticated,
     * otherwise 403.
     *
     * Authorization keys on model ownership, NOT on the route-name permission
     * (the route middleware already enforces that). This removes the former
     * early return that made the per-tenant ownership branch dead code, so the
     * boundary can no longer be short-circuited by iterating IDs (F-01).
     *
     * @param  object  $request
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @return void
     */
    public static function assertUserMayAccessModel($request, $model = null): void
    {
        $authUser = $request->user();
        if (!$authUser) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        // Full-access roles are never tenant-restricted.
        if ($authUser->hasAnyRole([config('vc.superadmin'), 'admin', 'billing_admin'])) {
            return;
        }

        // Tenant-scoped roles must own the model. Object routes must supply one;
        // fail closed when they do not.
        if (!($model instanceof Model)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $attributes = $model->getAttributes();

        // Per-account/branch records (e.g. SOA): match against assigned accounts.
        if (array_key_exists('account_code', $attributes)) {
            if (self::userOwnsAccount($authUser, $model->account_code, $model->branch_code ?? null)) {
                return;
            }
            abort(Response::HTTP_FORBIDDEN);
        }

        // Per-user records (e.g. concerns, account payments): match owner id.
        if (array_key_exists('user_id', $attributes)) {
            if ((int) $model->user_id === (int) $authUser->id) {
                return;
            }
            abort(Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN);
    }

    /**
     * Determine whether a tenant-scoped user is assigned to the given account
     * (and branch, when the assignment is branch-specific).
     *
     * Mirrors the row-level boundary used by the list queries
     * ({@see \App\Models\Soa::applyUserAccountRestriction()} and
     * {@see \App\Helpers\SqlDatabase::applyCholderAccountFilters()}):
     *  - broker               -> any account belonging to the agent
     *  - account_branch_admin -> the user's first account (+ branch if set)
     *  - group_account_admin  -> any of the user's accounts (+ branch per account)
     *
     * @param  \App\Models\User  $authUser
     * @param  string|null  $accountCode
     * @param  string|null  $branchCode
     * @return bool
     */
    private static function userOwnsAccount($authUser, ?string $accountCode, ?string $branchCode): bool
    {
        if ($accountCode === null || $accountCode === '') {
            return false;
        }

        if ($authUser->hasRole('broker')) {
            $agentAccounts = (new SqlDatabase(Server::HMS))
                ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);

            return $agentAccounts->contains(fn ($code) => (string) $code === (string) $accountCode);
        }

        if ($authUser->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            if (!$firstAccount || (string) $firstAccount->account_code !== (string) $accountCode) {
                return false;
            }
            if (!empty($firstAccount->branch_code)) {
                return (string) $firstAccount->branch_code === (string) $branchCode;
            }
            return true;
        }

        if ($authUser->hasRole('group_account_admin')) {
            return $authUser->userAccounts->contains(function ($ua) use ($accountCode, $branchCode) {
                if ((string) $ua->account_code !== (string) $accountCode) {
                    return false;
                }
                if (!empty($ua->branch_code)) {
                    return (string) $ua->branch_code === (string) $branchCode;
                }
                return true;
            });
        }

        return false;
    }

    /**
     * Stream a stored file as inline preview response.
     */
    public static function previewStoredFile(string $diskName, ?string $filePath)
    {
        $safePath = self::sanitizePreviewPath($filePath);
        if (!$safePath) {
            abort(Response::HTTP_BAD_REQUEST, 'File path is required');
        }

        $disk = Storage::disk($diskName);

        if (!$disk->exists($safePath)) {
            abort(Response::HTTP_NOT_FOUND, 'File not found');
        }

        $stream = $disk->readStream($safePath);
        if (!is_resource($stream)) {
            abort(Response::HTTP_NOT_FOUND, 'File is not readable');
        }

        $mimeType = 'application/octet-stream';
        try {
            $mimeType = $disk->mimeType($safePath) ?? $mimeType;
        } catch (\Throwable $e) {
            // Keep default mime type when unavailable.
        }

        $fileName = basename($safePath);
        $fileSize = null;
        try {
            $fileSize = $disk->size($safePath);
        } catch (\Throwable $e) {
            // Omit content length when unavailable.
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, array_filter([
            'Content-Type' => $mimeType,
            'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
            'Content-Length' => $fileSize,
        ]));
    }

    /**
     * Create an encrypted short-lived token for file preview.
     */
    public static function createFilePreviewToken(
        string $diskName,
        string $filePath,
        int $userId,
        ?int $ttlMinutes = null
    ): string {
        $safePath = self::sanitizePreviewPath($filePath);
        if (!$safePath) {
            throw new \InvalidArgumentException('Invalid file path.');
        }

        $expiresAt = now()->addMinutes($ttlMinutes ?? config('vc.file_preview_token_ttl_minutes'))->timestamp;

        return Crypt::encryptString(json_encode([
            'disk' => $diskName,
            'path' => $safePath,
            'uid' => $userId,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt and validate file preview token payload.
     *
     * @return array{disk: string, path: string, uid: int, exp: int}
     */
    public static function parseFilePreviewToken(string $token): array
    {
        try {
            $decoded = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid preview token.');
        }

        if (
            !is_array($decoded)
            || !isset($decoded['disk'], $decoded['path'], $decoded['uid'], $decoded['exp'])
            || !is_string($decoded['disk'])
            || !is_string($decoded['path'])
            || !is_numeric($decoded['uid'])
            || !is_numeric($decoded['exp'])
        ) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid preview token.');
        }

        $path = self::sanitizePreviewPath($decoded['path']);
        if (!$path) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid preview token.');
        }

        return [
            'disk' => $decoded['disk'],
            'path' => $path,
            'uid' => (int) $decoded['uid'],
            'exp' => (int) $decoded['exp'],
        ];
    }

    /**
     * Validate token against user+disk and stream the file.
     */
    public static function previewStoredFileFromToken(
        string $token,
        string $expectedDisk,
        ?int $currentUserId
    ) {
        if ($token === '') {
            abort(Response::HTTP_BAD_REQUEST, 'Preview token is required.');
        }

        $payload = self::parseFilePreviewToken($token);

        if ($payload['disk'] !== $expectedDisk) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid preview token.');
        }

        if ($payload['exp'] < now()->timestamp) {
            abort(Response::HTTP_FORBIDDEN, 'Preview token expired.');
        }

        if ($currentUserId === null || $payload['uid'] !== $currentUserId) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return self::previewStoredFile($expectedDisk, $payload['path']);
    }

    /**
     * Normalize and validate a relative storage path for preview.
     */
    private static function sanitizePreviewPath(?string $filePath): ?string
    {
        if ($filePath === null) {
            return null;
        }

        $normalized = trim(str_replace('\\', '/', $filePath));
        if ($normalized === '') {
            return null;
        }

        // Require disk-relative paths and block traversal/null-byte patterns.
        if (
            str_starts_with($normalized, '/')
            || str_contains($normalized, '../')
            || str_contains($normalized, '..\\')
            || str_contains($normalized, "\0")
        ) {
            abort(Response::HTTP_FORBIDDEN, 'Invalid file path');
        }

        return $normalized;
    }
}
