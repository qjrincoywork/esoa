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
        'color',
        'ref_id',
        'order_number',
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
            ->orderBy('order_number')
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
        if (!$this->permission_id) {
            return true;
        }

        return $user->hasPermissionTo($this->permission);
    }

    public function getNavigationModules(array $params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        $query = self::with(['navigation:id,name', 'permission:id,name'])
            ->when(isset($params['search_string']), fn ($q) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'LIKE', '%' . $params['search_string'] . '%')
                       ->orWhere('slug', 'LIKE', '%' . $params['search_string'] . '%')
                )
            )
            ->when(isset($params['navigation_id']), fn ($q) =>
                $q->where('navigation_id', $params['navigation_id'])
            )
            ->orderBy('navigation_id')
            ->orderBy('order_number')
            ->orderBy('id', 'desc');

        if (auth()->user()?->hasRole('superadmin')) {
            $query->withTrashed();
        }

        return $query->paginate($perPage);
    }

    public function saveNavigationModule(array $data): void
    {
        $data += ['created_by' => auth()->id()];

        if (isset($data['id'])) {
            $module = self::findOrFail($data['id']);
            $module->update($data);
        } else {
            self::create($data);
        }
    }
}
