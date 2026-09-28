<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
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
     * SQL Server hands some integer columns back as strings; cast them so the client
     * can compare them strictly (status badges, pre-selected form options).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'navigation_id' => 'integer',
            'permission_id' => 'integer',
            'ref_id'        => 'integer',
            'order_number'  => 'integer',
            'status'        => 'integer',
        ];
    }

    /**
     * Get the navigation that owns this module.
     */
    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    /**
     * Get the active (status = 1) child modules of this module, ordered by order_number
     * (has-many NavigationModule via ref_id).
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

    /**
     * Get a paginated list of navigation modules with their navigation and permission eager-loaded.
     *
     * Optionally filters by name/slug (search_string) and navigation_id, ordered by
     * navigation_id, order_number, then newest id. Superadmins also see soft-deleted rows.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getNavigationModules(array $params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        $query = self::with(['navigation:id,name', 'permission:id,name'])
            ->when(isset($params['search_string']), fn ($q) => $q->search($params['search_string']))
            ->when(isset($params['navigation_id']), fn ($q) =>
                $q->where('navigation_id', $params['navigation_id'])
            )
            // 0 means "top-level only" (ref_id IS NULL); a positive id filters to that module's children.
            ->when(isset($params['ref_id']), fn ($q) =>
                ((int) $params['ref_id']) === 0
                    ? $q->whereNull('ref_id')
                    : $q->where('ref_id', (int) $params['ref_id'])
            )
            ->orderBy('navigation_id')
            ->orderBy('order_number')
            ->orderBy('id', 'desc');

        if (auth()->user()?->hasRole('superadmin')) {
            $query->withTrashed();
        }

        return $query->paginate($perPage);
    }

    /**
     * Match modules whose name or slug contains the term.
     *
     * LIKE wildcards in the term are escaped so a search for `users_` finds that literal
     * text rather than any character after "users" — bracket syntax on SQL Server, the
     * default backslash escape elsewhere.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $escapes = $query->getConnection()->getDriverName() === 'sqlsrv'
            ? ['[' => '[[]', '%' => '[%]', '_' => '[_]']
            : ['\\' => '\\\\', '%' => '\\%', '_' => '\\_'];

        $like = '%' . strtr($term, $escapes) . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'LIKE', $like)
            ->orWhere('slug', 'LIKE', $like));
    }

    /**
     * Create or update a navigation module.
     *
     * Updates the existing record when the data contains an 'id' (findOrFail), otherwise
     * creates a new one stamped with the authenticated user as created_by — an update
     * never rewrites who created the module.
     */
    public function saveNavigationModule(array $data): self
    {
        if (isset($data['id'])) {
            $module = self::findOrFail($data['id']);
            $module->update(Arr::except($data, ['id', 'created_by']));

            return $module;
        }

        return self::create($data + ['created_by' => auth()->id()]);
    }
}
