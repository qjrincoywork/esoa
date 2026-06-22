<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    SoftDeletes,
    Relations\BelongsTo,
    Relations\BelongsToMany
};

class OfficialReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_payment_id',
        'or_number',
        'or_date',
        'amount',
        'file',
        'remarks',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'or_date' => 'date',
    ];

    /** Billing user who issued the OR. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Remittance advice that triggered this OR (optional). */
    public function accountPayment(): BelongsTo
    {
        return $this->belongsTo(AccountPayment::class, 'account_payment_id');
    }

    /** SOAs covered by this official receipt. */
    public function soas(): BelongsToMany
    {
        return $this->belongsToMany(
            Soa::class,
            'soa_official_receipts',
            'official_receipt_id',
            'soa_id'
        );
    }

    public function getOfficialReceipts(array $params)
    {
        $authUser = auth()->user();

        return self::with(['user', 'soas'])
            ->when(
                $authUser && $authUser->hasRole('superadmin'),
                fn ($q) => $q->withTrashed()
            )
            ->when(!empty($params['or_number'] ?? null), fn ($q) => $q->where('or_number', 'like', '%' . $params['or_number'] . '%'))
            ->when(!empty($params['or_date'] ?? null), fn ($q) => $q->whereDate('or_date', $params['or_date']))
            ->when(
                array_key_exists('account_payment_id', $params) && $params['account_payment_id'] !== null,
                fn ($q) => $q->where('account_payment_id', (int) $params['account_payment_id'])
            )
            ->when(!empty($params['soa_id'] ?? null), function ($q) use ($params) {
                $q->whereHas('soas', fn ($sq) => $sq->where('soas.id', (int) $params['soa_id']));
            })
            ->latest('id')
            ->paginate($params['per_page'] ?? config('vc.default_pages'));
    }
}
