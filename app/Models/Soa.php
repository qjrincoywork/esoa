<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\HasMany, Relations\HasOne, Relations\BelongsTo};

class Soa extends Model
{
    /** @use HasFactory<\Database\Factories\SoaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'soa_number',
        'account_code',
        'branch_code',
        'billing_ref',
        'bill_type',
        'status',
        'bill_date',
        'due_date',
        'period_date_from',
        'period_date_to',
        'amount',
        'amount_paid',
        'payment_adjustment',
        'balance',
        'file_pdf',
        'file_xls',
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
     * Get all activities associated with this SOA.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\SoaActivity>
     */
    public function soaActivity(): HasMany
    {
        return $this->hasMany(SoaActivity::class, 'soa_id');
    }


    /**
     * Retrieves a paginated list of SOA records from the database.
     *
     * @param array $params
     * @return \Illuminate\Pagination\Paginator
     */
    public function getSoas($params)
    {
        // Pagination
        $perPage = $params['per_page'] ?? config('vc.default_pages');
        $result = self::with('soaActivity')
            ->when(isset($params['soanum']), function ($query) use ($params) {
                $query->where('up_soanum', 'LIKE', '%' . $params['soanum'] . '%');
            })
            ->orderBy('id', OrderType::DESC);

        if (auth()->user() && !auth()->user()->hasRole('superadmin')) {
            $result->withTrashed();
        }

        return $result->paginate($perPage);
    }

    public function saveSoa(array $data) {
        if (isset($data['id'])) {
            $user = self::find($data['id']);
            $user->update($data);
        } else {
            $user = self::create($data);
        }
    }
}
