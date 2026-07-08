<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    SoftDeletes,
    Relations\BelongsTo,
    Relations\BelongsToMany
};

class AccountPayment extends Model
{
    /** @use HasFactory<\Database\Factories\AccountPaymentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'deposit_date',
        'mode_of_payment',
        'image',
        'excel',
        'pdf',
        'remarks',
    ];

    /**
     * Get the user who recorded this account payment (belongs-to User via user_id).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the SOAs this payment is applied to, through the soa_account_payments pivot (many-to-many).
     *
     * @return BelongsToMany
     */
    public function soas(): BelongsToMany
    {
        return $this->belongsToMany(
            Soa::class,
            'soa_account_payments',
            'account_payment_id',
            'soa_id'
        );
    }

    /**
     * Get a paginated list of account payments with the recording user eager-loaded.
     *
     * Brokers and account/group admins are scoped to their own payments; superadmins
     * also see soft-deleted rows. Optionally filters by deposit date, mode of payment,
     * creator username, and remarks, ordered by newest id first.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAccountPayments(array $params)
    {
        $authUser = auth()->user();

        return self::with(['user'])
            ->when($authUser && ($authUser->hasRole('broker') || $authUser->hasAnyRole(['account_branch_admin', 'group_account_admin'])), function ($query) use ($authUser) {
                $query->where('user_id', $authUser->id);
            })
            ->when($authUser && $authUser->hasRole('superadmin'), function ($query) {
                $query->withTrashed();
            })
            ->when(!empty($params['deposit_date'] ?? null), function ($query) use ($params) {
                $query->whereDate('deposit_date', $params['deposit_date']);
            })
            ->when(array_key_exists('mode_of_payment', $params) && $params['mode_of_payment'] !== null && $params['mode_of_payment'] !== '', function ($query) use ($params) {
                $query->where('mode_of_payment', (int) $params['mode_of_payment']);
            })
            ->when(!empty($params['created_by'] ?? null), function ($query) use ($params) {
                $createdBy = trim((string) $params['created_by']);
                $query->whereRelation('user', 'username', 'like', '%' . $createdBy . '%');
            })
            ->when(!empty($params['remarks'] ?? null), function ($query) use ($params) {
                $query->where('remarks', 'like', '%' . $params['remarks'] . '%');
            })
            ->latest('id')
            ->paginate($params['per_page'] ?? config('vc.default_pages'));
    }
}
