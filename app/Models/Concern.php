<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{
    Model,
    SoftDeletes,
    Relations\BelongsTo,
    Relations\BelongsToMany
};

class Concern extends Model
{
    /** @use HasFactory<\Database\Factories\ConcernFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'status',
        'attachment',
    ];

    /**
     * Get the user who raised this concern (belongs-to User via user_id).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the SOAs this concern is linked to, through the soa_concerns pivot (many-to-many).
     *
     * @return BelongsToMany
     */
    public function soas(): BelongsToMany
    {
        return $this->belongsToMany(
            Soa::class,
            'soa_concerns',
            'concern_id',
            'soa_id'
        );
    }

    /**
     * Get a paginated list of concerns with the user and linked SOAs eager-loaded.
     *
     * Brokers and account/group admins are scoped to their own concerns; superadmins
     * also see soft-deleted rows. Optionally filters by title, description, type, and
     * status, ordered by newest id first.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getConcerns($params)
    {
        $authUser = auth()->user();
        $concerns = self::with(['user', 'soas'])
            ->when($authUser->hasRole('broker') || $authUser->hasAnyRole(['account_branch_admin', 'group_account_admin']), function ($query) use ($authUser) {
                    $query->where('user_id', $authUser->id);
                }
            )
            ->when($authUser->hasRole('superadmin'), function ($query) {
                $query->withTrashed();
            })
            ->when(!empty($params['title'] ?? null), function ($query) use ($params) {
                $query->where('title', 'like', '%' . $params['title'] . '%');
            })
            ->when(!empty($params['description'] ?? null), function ($query) use ($params) {
                $query->where('description', 'like', '%' . $params['description'] . '%');
            })
            ->when(array_key_exists('type', $params) && !empty($params['type']), function ($query) use ($params) {
                $query->where('type', (int) $params['type']);
            })
            ->when(array_key_exists('status', $params) && !empty($params['status']), function ($query) use ($params) {
                $query->where('status', (int) $params['status']);
            })
            ->latest('id')
            ->paginate($params['per_page']);

        return $concerns;
    }
}
