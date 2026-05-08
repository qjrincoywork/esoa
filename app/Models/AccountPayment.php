<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    SoftDeletes,
    Relations\BelongsTo
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
        'remittance_advice',
        'remarks',
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

    public function getAccountPayments(array $params)
    {
        $authUser = auth()->user();

        return self::with(['user'])
            ->when($authUser && ($authUser->hasRole('broker') || $authUser->hasRole('account_branch_admin')), function ($query) use ($authUser) {
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
            ->when(!empty($params['remittance_advice'] ?? null), function ($query) use ($params) {
                $query->where('remittance_advice', 'like', '%' . $params['remittance_advice'] . '%');
            })
            ->when(!empty($params['remarks'] ?? null), function ($query) use ($params) {
                $query->where('remarks', 'like', '%' . $params['remarks'] . '%');
            })
            ->latest('id')
            ->paginate($params['per_page'] ?? config('vc.default_pages'));
    }
}
