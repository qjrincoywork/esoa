<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\HasMany, Relations\BelongsTo};
use App\Models\User;

class Navigation extends Model
{
    /** @use HasFactory<\Database\Factories\NavigationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'label',
        'icon',
        'status',
        'created_by',
    ];

    /**
     * Get the modules for this navigation.
     */
    public function modules(): HasMany
    {
        return $this->hasMany(NavigationModule::class)->where('status', 1);
    }

    /**
     * Get all modules (including inactive).
     */
    public function allModules(): HasMany
    {
        return $this->hasMany(NavigationModule::class);
    }

    /**
     * Get the user who created this navigation.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get modules that are accessible by the given user.
     * Returns a collection of NavigationModule models.
     */
    public function accessibleModules($user)
    {
        $query = $this->modules()->with('subModules');

        // If user is superadmin, return all modules
        // if ($user && $user->hasRole('superadmin')) {
        //     return $query->get();
        // }

        // Filter modules based on user permissions
        if ($user) {
            $permissionIds = $user->getAllPermissions()->pluck('id')->toArray();
            
            return $query->where(function ($q) use ($permissionIds) {
                $q->whereNull('permission_id') // Modules without permission requirement
                  ->orWhereIn('permission_id', $permissionIds) // Modules user has permission for
                  ->whereNull('ref_id');
            })->get();
        }

        // If no user, return only modules without permission requirement
        return $query->whereNull('permission_id')->get();
    }
}
