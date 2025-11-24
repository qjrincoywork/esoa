<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

class NavigationModule extends Model
{
    /** @use HasFactory<\Database\Factories\NavigationModuleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'url',
        'icon',
        'navigation_id',
        'permission_id',
        'status',
        'created_by',
    ];

    /**
     * Get the navigation that owns this module.
     */
    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    /**
     * Get sub modules.
     */
    public function subModules(): HasMany
    {
        return $this->hasMany(NavigationModule::class, 'ref_id')
            ->where('status', 1);
    }

    /**
     * Get the permission required to access this module.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Check if a user can access this module.
     * If no permission is set, the module is accessible to all authenticated users.
     */
    public function canBeAccessedBy($user): bool
    {
        // If no permission is required, allow access
        if (!$this->permission_id) {
            return true;
        }

        // Check if user has the required permission
        return $user->hasPermissionTo($this->permission);
    }
}
