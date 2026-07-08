<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\HasMany, Relations\HasOne, Relations\BelongsTo};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use App\Enums\UserType;
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
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
        ];
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
     * Get users with optional filters and pagination.
     *
     * @param array $params
     * @return \Illuminate\Pagination\Paginator
     */
    public function getUsers(array $params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = self::query()
            ->when(isset($params['search_string']), function ($query) use ($params) {
                $query->where('email', 'LIKE', '%' . $params['search_string'] . '%')
                    ->orWhere('username', 'LIKE', '%' . $params['search_string'] . '%');
            })
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

        return $result->paginate($perPage);
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
     * Sync the user_accounts table based on the user type and submitted form data.
     * - ACCOUNT_BRANCH_ADMIN (type 2): single account_code/branch_code as top-level fields.
     * - GROUP_ACCOUNT_ADMIN  (type 4): array of {account_type, account_code, branch_code} entries.
     * - Other types: remove all user_accounts.
     */
    private function syncUserAccounts(self $user, array $data): void
    {
        $type = (int) ($data['type'] ?? 0);

        if ($type === UserType::ACCOUNT_BRANCH_ADMIN && !empty($data['account_code'])) {
            $user->userAccounts()->delete();
            $user->userAccounts()->create([
                'account_type' => $data['account_type'] ?? null,
                'account_code' => $data['account_code'],
                'branch_code'  => $data['branch_code'] ?? null,
            ]);
            return;
        }

        if ($type === UserType::GROUP_ACCOUNT_ADMIN && !empty($data['user_accounts'])) {
            $user->userAccounts()->delete();
            foreach ($data['user_accounts'] as $ua) {
                if (!empty($ua['account_code'])) {
                    $user->userAccounts()->create([
                        'account_type' => $ua['account_type'] ?? null,
                        'account_code' => $ua['account_code'],
                        'branch_code'  => $ua['branch_code'] ?? null,
                    ]);
                }
            }
            return;
        }

        // VC_EMPLOYEE, BROKER, or type change — clear any residual accounts
        if (!in_array($type, [UserType::ACCOUNT_BRANCH_ADMIN, UserType::GROUP_ACCOUNT_ADMIN])) {
            $user->userAccounts()->delete();
        }
    }
}
