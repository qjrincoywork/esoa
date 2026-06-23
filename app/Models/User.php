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

    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'user_id');
    }

    public function accountPayments(): HasMany
    {
        return $this->hasMany(AccountPayment::class, 'user_id');
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(Soa::class, 'user_id');
    }

    public function soaActivities(): HasMany
    {
        return $this->hasMany(SoaActivity::class, 'user_id');
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
        $result = self::when(isset($params['search_string']), function ($query) use ($params) {
                $query->where('email', 'LIKE', '%' . $params['search_string'] . '%');
            })
            ->when(isset($params['search_string']), function ($query) use ($params) {
                $query->where('username', 'LIKE', '%' . $params['search_string'] . '%');
            })
            ->when(isset($params['is_active']), function ($query) use ($params) {
                $query->where('is_active', $params['is_active']);
            })
            ->with('userDetail')
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

    public function saveUser(array $data, ?self $target = null): void
    {
        if ($target !== null) {
            $target->update($data);
            $target->userDetail()->updateOrCreate(['user_id' => $target->id], $data);
        } else {
            $data += ['password' => Hash::make(Str::random(12))];
            $user = self::create($data);

            $data += ['user_id' => $user->id];
            $user->userDetail()->create($data);
        }
    }
}
