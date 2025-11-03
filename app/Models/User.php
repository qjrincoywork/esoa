<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    // use HasRoles;
    use HasFactory, HasRoles;
    // use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

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
        return $this->hasOne(UserDetail::class);
    }

    public function getUsers(array $params)
    {
        // $users = auth()->user()->users()
        // Pagination
        // $page = $params['page'] ?? config('vc.default_start_page');
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = self::when(isset($params['username']), function ($query) use ($params) {
                $query->where('username', 'LIKE', '%' . $params['username'] . '%');
            })
            ->when(isset($params['email']), function ($query) use ($params) {
                $query->where('email', 'LIKE', '%' . $params['email'] . '%');
            })
            ->when(isset($params['is_active']), function ($query) use ($params) {
                $query->where('is_active', $params['is_active']);
            })
            ->with('userDetail')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // dd($result, 'hits users');
        // dd($result->toSql(), 'hits users');
        return $result;
    }
}
