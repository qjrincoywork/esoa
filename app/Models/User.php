<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
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
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $result;
    }

    public function saveUser(array $data) {
        if (isset($data['id'])) {
            $user = self::find($data['id']);
            $user->update($data);
            $user->userDetail()->updateOrCreate(['user_id' => $user->id], $data);
        } else {
            $data += ['password' => Hash::make(config('vc.default_password'))];
            $user = self::create($data);

            $data += ['user_id' => $user->id];
            $user->userDetail()->create($data);
        }
    }
}
