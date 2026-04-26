<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

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
        'billing_invoice',
        'type',
        'title',
        'description',
        'status',
        'attachment',
    ];

    /**
     * Method: user
     * This method defines the relationship between the User model and the UserDetail model.
     *
     * @return BelongsTo The relationship between User and UserDetail models.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Method: soa
     * This method defines the relationship between the User model and SoaActivity Model.
     *
     * @return BelongsTo The relationship between User and SoaActivity Model.
     */
    public function soa(): BelongsTo
    {
        return $this->belongsTo(Soa::class, 'billing_invoice', 'soa_number');
    }

    public function getConcerns($params)
    {
        $authUser = auth()->user();
        $concerns = self::with(['user', 'soa'])
            ->when($authUser->hasRole('broker') || $authUser->hasRole('account_branch_admin'), function ($query) use ($authUser) {
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
            ->paginate($params['per_page']);

        return $concerns;
    }
}
