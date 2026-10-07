<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes, Relations\HasMany, Relations\HasOne, Relations\BelongsTo};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Enums\{AuditEvent, CredentialAccess, CredentialStatus, PermissionAssignmentMode, UserType};
use App\Support\AuthenticationAudit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements AuthorizableContract, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, Authorizable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'temporary_password_expires_at',
        'is_active',
        'is_approved',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'temporary_password_expires_at' => 'datetime',
            'credentials_sent_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Stamp password_changed_at whenever an existing user's password is replaced by one
     * of their own.
     *
     * Every self-service path (settings, forgot-password reset) clears the temporary
     * expiry alongside the new password, while issuing a temporary password sets one —
     * so "password changed and no temporary expiry" is exactly "the user chose it",
     * wherever the change came from. The same moment goes on the user's audit trail
     * ({@see AuthenticationAudit}), once the change has actually been saved.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->exists && $user->isDirty('password') && $user->temporary_password_expires_at === null) {
                $user->password_changed_at = now();
            }
        });

        static::updated(function (self $user): void {
            if ($user->wasChanged('password_changed_at')) {
                AuthenticationAudit::record($user, AuditEvent::PASSWORD_CHANGED);
            }
        });
    }

    /**
     * Where this user's credentials stand ({@see CredentialStatus}).
     */
    public function credentialStatus(): int
    {
        return CredentialStatus::resolve($this->credentials_sent_at, $this->temporary_password_expires_at);
    }

    /**
     * Whether the user has used the credentials they were sent ({@see CredentialAccess}):
     * signed in since they were sent, or already replaced the temporary password.
     */
    public function hasAccessedCredentials(): bool
    {
        if ($this->credentials_sent_at === null) {
            return false;
        }

        return $this->credentialStatus() === CredentialStatus::UPDATED
            || ($this->last_login_at !== null && $this->last_login_at->gte($this->credentials_sent_at));
    }

    /**
     * Record a sign-in without touching updated_at or firing model events — a login is
     * not an edit of the user, and must not show up as one in the audit trail.
     */
    public function recordLogin(): void
    {
        static::withoutTimestamps(fn () => $this->forceFill(['last_login_at' => now()])->saveQuietly());
    }

    /**
     * Constrain to users in the given {@see CredentialStatus} — the query form of
     * {@see CredentialStatus::resolve()}.
     */
    public function scopeCredentialStatus(Builder $query, int $status): Builder
    {
        return match ($status) {
            CredentialStatus::NOT_SENT => $query->whereNull('credentials_sent_at'),
            CredentialStatus::UPDATED => $query->whereNotNull('credentials_sent_at')->whereNull('temporary_password_expires_at'),
            CredentialStatus::EXPIRED => $query->whereNotNull('credentials_sent_at')->where('temporary_password_expires_at', '<=', now()),
            CredentialStatus::TEMPORARY => $query->whereNotNull('credentials_sent_at')->where('temporary_password_expires_at', '>', now()),
            default => $query,
        };
    }

    /**
     * Constrain to users who have (or have not) accessed the credentials they were sent
     * — the query form of {@see hasAccessedCredentials()}. Users never sent credentials
     * match neither state.
     */
    public function scopeCredentialAccess(Builder $query, int $access): Builder
    {
        $query->whereNotNull('credentials_sent_at');

        if ($access === CredentialAccess::ACCESSED) {
            return $query->where(fn (Builder $q) => $q
                ->whereNull('temporary_password_expires_at')
                ->orWhereColumn('last_login_at', '>=', 'credentials_sent_at'));
        }

        // Spelled out rather than NOT(accessed): a never-signed-in user has a NULL
        // last_login_at, the comparison is UNKNOWN, and NOT UNKNOWN would drop them from
        // both states instead of counting them here.
        return $query
            ->whereNotNull('temporary_password_expires_at')
            ->where(fn (Builder $q) => $q
                ->whereNull('last_login_at')
                ->orWhereColumn('last_login_at', '<', 'credentials_sent_at'));
    }

    /**
     * Method: userDetail
     * This method defines the relationship between the User model and the UserDetail model.
     *
     * @return HasOne The relationship between User and UserDetail models.
     */
    public function userDetail(): HasOne
    {
        return $this->hasOne(UserDetail::class, 'user_id');
    }

    /**
     * Get the concerns raised by this user (has-many Concern via user_id).
     *
     * @return HasMany
     */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'user_id');
    }

    /**
     * Get the account payments recorded by this user (has-many AccountPayment via user_id).
     *
     * @return HasMany
     */
    public function accountPayments(): HasMany
    {
        return $this->hasMany(AccountPayment::class, 'user_id');
    }

    /**
     * Get the SOAs (billing invoices) owned by this user (has-many Soa via user_id).
     *
     * @return HasMany
     */
    public function billingInvoices(): HasMany
    {
        return $this->hasMany(Soa::class, 'user_id');
    }

    /**
     * Get the SOA activity entries performed by this user (has-many SoaActivity via user_id).
     *
     * @return HasMany
     */
    public function soaActivities(): HasMany
    {
        return $this->hasMany(SoaActivity::class, 'user_id');
    }

    /**
     * Get the account/branch assignments for this user (has-many UserAccount via user_id).
     *
     * @return HasMany
     */
    public function userAccounts(): HasMany
    {
        return $this->hasMany(UserAccount::class, 'user_id');
    }

    /**
     * Whether this user's type (user_details.type) is one of the given types.
     *
     * @param int|array<int, int> $types
     */
    public function hasUserType(int|array $types): bool
    {
        $type = $this->userDetail?->type;

        return $type !== null && in_array((int) $type, (array) $types, true);
    }

    /**
     * The account/branch pairs whose billing invoices are attributed to this user.
     *
     * Mirrors the row-level rule in {@see \App\Models\Soa::applyUserAccountRestriction()}
     * so a report and the query it drills into can never describe different rows:
     *  - account_branch_admin -> the first assignment only (with its branch, when set)
     *  - group_account_admin  -> every assignment
     *  - anyone else          -> none (their data is attributed by ownership instead)
     *
     * A pair with a null `branch_code` covers every branch of that account.
     *
     * @return array<int, array{account_code: string, branch_code: string|null}>
     */
    public function scopedAccountPairs(): array
    {
        $accounts = $this->userAccounts;

        if ($this->hasRole('account_branch_admin')) {
            $first = $accounts->first();

            return $first
                ? [['account_code' => (string) $first->account_code, 'branch_code' => $first->branch_code ?: null]]
                : [];
        }

        if ($this->hasRole('group_account_admin')) {
            return $accounts
                ->map(static fn ($account): array => [
                    'account_code' => (string) $account->account_code,
                    'branch_code' => $account->branch_code ?: null,
                ])
                ->all();
        }

        return [];
    }

    /**
     * Get users with optional filters and pagination.
     *
     * @param array $params
     * @return \Illuminate\Pagination\Paginator
     */
    public function getUsers(array $params)
    {
        return $this->listQuery($params)->paginate($params['per_page'] ?? config('vc.default_pages'));
    }

    /**
     * The filtered user query behind the list, shared with the credential reports so an
     * export contains exactly what the filtered list shows.
     *
     * The search is grouped so an email-or-username match can never escape the other
     * filters. A bulk lookup (`search_terms`) lists the users matching any of its
     * entries — or only `search_term_focus`, when one entry is singled out — each
     * matched the way {@see searchTermCondition()} says. Callers add their own eager
     * loads beyond the user detail.
     */
    public function listQuery(array $params): Builder
    {
        $exactMatch = !empty($params['exact_match']);
        $searchTerms = isset($params['search_term_focus'])
            ? [$params['search_term_focus']]
            : ($params['search_terms'] ?? []);

        $result = self::query()
            ->when(isset($params['search_string']), function ($query) use ($params) {
                $query->where(fn ($q) => $q
                    ->where('email', 'LIKE', '%' . $params['search_string'] . '%')
                    ->orWhere('username', 'LIKE', '%' . $params['search_string'] . '%'));
            })
            ->when($searchTerms !== [], function ($query) use ($searchTerms, $exactMatch) {
                $query->where(function ($q) use ($searchTerms, $exactMatch) {
                    foreach ($searchTerms as $term) {
                        $q->orWhereRaw(...$this->searchTermCondition($term, $exactMatch));
                    }
                });
            })
            ->when(isset($params['credential_status']), fn ($query) => $query->credentialStatus((int) $params['credential_status']))
            ->when(isset($params['credential_access']), fn ($query) => $query->credentialAccess((int) $params['credential_access']))
            ->when(isset($params['is_active']), function ($query) use ($params) {
                $query->where('is_active', $params['is_active']);
            })
            ->when(isset($params['type']), function ($query) use ($params) {
                $query->whereHas('userDetail', fn ($q) => $q->where('type', $params['type']));
            })
            ->when(
                isset($params['department_id']) && isset($params['type']) && (int) $params['type'] === UserType::VC_EMPLOYEE,
                fn ($query) => $query->whereHas('userDetail', fn ($q) => $q->where('department_id', $params['department_id']))
            )
            ->with('userDetail.department')
            ->orderBy('id', 'desc');

        $authUser = auth()->user();
        if ($authUser && (
            $authUser->hasAnyRole(['superadmin', 'admin']) ||
            $authUser->hasAnyPermission(['users.destroy'])
        )) {
            $result->withTrashed();
        }

        return $result;
    }

    /**
     * How many users each entry of a bulk lookup matches, in the order it was entered.
     *
     * Counted under every other list filter — so a count is what the list would show
     * for that entry — and in a single pass: one conditional SUM per entry rather than
     * a query each. Empty when no bulk lookup was asked for.
     *
     * @param  array<string, mixed>  $params  The validated list filters.
     * @return array<int, array{term: string, count: int}>
     */
    public function searchTermMatches(array $params): array
    {
        $terms = $params['search_terms'] ?? [];

        if ($terms === []) {
            return [];
        }

        $exactMatch = !empty($params['exact_match']);
        $columns = [];
        $bindings = [];

        foreach ($terms as $index => $term) {
            [$condition, $termBindings] = $this->searchTermCondition($term, $exactMatch);
            $columns[] = "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) AS match_{$index}";
            array_push($bindings, ...$termBindings);
        }

        // Counted across the whole lookup, not just the entry singled out on the list.
        $counts = $this->listQuery(array_diff_key($params, array_flip(['search_terms', 'search_term_focus'])))
            ->toBase()
            ->reorder()
            ->selectRaw(implode(', ', $columns), $bindings)
            ->first();

        return array_map(
            fn (string $term, int $index) => ['term' => $term, 'count' => (int) ($counts->{"match_{$index}"} ?? 0)],
            $terms,
            array_keys($terms)
        );
    }

    /**
     * The SQL deciding whether a user matches one bulk-lookup entry, with its bindings:
     * the username or email equal to the entry when matching exactly, containing it
     * otherwise. The list filter and the per-entry counts share it, so a count never
     * disagrees with the rows the list then shows for that entry.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    protected function searchTermCondition(string $term, bool $exactMatch): array
    {
        $email = $this->qualifyColumn('email');
        $username = $this->qualifyColumn('username');

        return $exactMatch
            ? ["({$email} = ? OR {$username} = ?)", [$term, $term]]
            : ["({$email} LIKE ? OR {$username} LIKE ?)", ["%{$term}%", "%{$term}%"]];
    }

    /**
     * Create or update a user and their detail record.
     *
     * Credentials are no longer emailed here — a fresh temporary password is
     * issued and delivered when the account is verified (see UserController).
     *
     * @return self The created or updated user.
     */
    public function saveUser(array $data, ?self $target = null): self
    {
        if ($target !== null) {
            $target->update($data);
            $target->userDetail()->updateOrCreate(['user_id' => $target->id], $data);
            $this->syncUserAccounts($target, $data);

            return $target;
        }

        $user = new self();
        $user->fill($data);
        $user->withTemporaryPassword();
        $user->save();

        $data['user_id'] = $user->id;
        $user->userDetail()->create($data);
        $this->syncUserAccounts($user, $data);

        return $user;
    }

    /**
     * Generate a strong, random temporary password.
     */
    public static function generateTemporaryPassword(): string
    {
        return Str::password(16, letters: true, numbers: true, symbols: false, spaces: false);
    }

    /**
     * Assign a freshly generated temporary password (and reset its expiry
     * window) to this instance, returning the plain text so the caller can
     * deliver it by email. The value is hashed by the model's 'password' cast
     * on save; the plain text is never persisted.
     *
     * The caller is responsible for persisting via save()/update().
     */
    public function withTemporaryPassword(): string
    {
        $plainPassword = self::generateTemporaryPassword();

        $this->password = $plainPassword;
        $this->temporary_password_expires_at = now()->addHours(
            config('vc.temp_password_expires_hours', 72)
        );

        return $plainPassword;
    }

    /**
     * Apply a set of permissions to this user's direct grants in the given mode.
     *
     * Only direct grants (model_has_permissions) are touched; what the user inherits
     * through a role stays with the role. The permissions are resolved by the caller
     * once, so a bulk run applies the same models to every user without re-querying.
     * No cache flush is needed: Spatie caches permissions and their roles, while a
     * user's direct grants are read through the relation on each request.
     *
     * @param  Collection<int, Permission>  $permissions
     */
    public function applyDirectPermissions(Collection $permissions, string $mode = PermissionAssignmentMode::SYNC): void
    {
        match ($mode) {
            PermissionAssignmentMode::GIVE => $this->givePermissionTo($permissions),
            PermissionAssignmentMode::REVOKE => $this->revokePermissionTo($permissions),
            default => $this->syncPermissions($permissions),
        };
    }

    /**
     * Sync the user_accounts table based on the user type and submitted form data.
     * - ACCOUNT_BRANCH_ADMIN (type 2): single account_code/branch_code as top-level fields.
     * - GROUP_ACCOUNT_ADMIN  (type 4): array of {account_type, account_code, branch_code} entries.
     * - Other types: remove all user_accounts.
     *
     * The rows themselves are written by {@see UserAccount::syncForUser()}, which the
     * dedicated mapping screen also goes through, so both entry points normalise and
     * cap a mapping set the same way.
     */
    private function syncUserAccounts(self $user, array $data): void
    {
        $type = (int) ($data['type'] ?? 0);

        // VC_EMPLOYEE, BROKER, or type change — clear any residual accounts
        if (!UserType::allowsAccountMapping($type)) {
            $user->userAccounts()->delete();

            return;
        }

        $rows = UserType::allowsMultipleAccounts($type)
            ? ($data['user_accounts'] ?? [])
            : (!empty($data['account_code']) ? [$data] : []);

        // An absent set means the form did not manage these fields, not that access was
        // revoked — leave whatever is stored alone.
        if (empty($rows)) {
            return;
        }

        UserAccount::syncForUser($user, $rows, UserType::accountMappingLimit($type));
    }
}
