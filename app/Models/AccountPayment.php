<?php

namespace App\Models;

use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    SoftDeletes,
    Relations\BelongsTo,
    Relations\BelongsToMany,
    Relations\HasMany
};
use Illuminate\Support\Facades\Auth;

class AccountPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'amount',
        'deposit_date',
        'mode_of_payment',
        'image',
        'excel',
        'pdf',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function soas(): BelongsToMany
    {
        return $this->belongsToMany(
            Soa::class,
            'soa_account_payments',
            'account_payment_id',
            'soa_id'
        )->withPivot('applied_amount');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AccountPaymentActivity::class, 'account_payment_id');
    }

    /** Official receipts issued by billing against this remittance advice. */
    public function officialReceipts(): HasMany
    {
        return $this->hasMany(OfficialReceipt::class, 'account_payment_id');
    }

    /**
     * Record an audit activity entry for this remittance advice.
     *
     * @param  array{from?: array<string, mixed>|null, to: array<string, mixed>}  $snapshot
     */
    public function recordActivity(string $event, array $snapshot, ?User $actor = null): AccountPaymentActivity
    {
        $event = trim($event);
        if ($event === '' || strlen($event) > 191) {
            throw new \InvalidArgumentException('Invalid account payment activity event.');
        }
        if (!isset($snapshot['to']) || !is_array($snapshot['to'])) {
            throw new \InvalidArgumentException('Account payment activity snapshot must include a "to" array.');
        }
        $from = $snapshot['from'] ?? null;
        if ($from !== null && !is_array($from)) {
            throw new \InvalidArgumentException('Account payment activity "from" must be an array or null.');
        }

        $user = $actor ?? Auth::user();
        if (!$user instanceof User) {
            throw new \RuntimeException('Account payment activity requires an authenticated user.');
        }

        $name = $user->username ?? $user->email ?? (string) $user->id;

        return $this->activities()->create([
            'user_id' => (int) $user->getAuthIdentifier(),
            'name'    => $name,
            'event'   => $event,
            'from'    => $from,
            'to'      => $snapshot['to'],
        ]);
    }

    /**
     * Total amount already applied to SOAs for this remittance advice.
     */
    public function totalApplied(): float
    {
        return (float) $this->soas()->sum('soa_account_payments.applied_amount');
    }

    /**
     * Remaining unapplied balance — this is also the available credit for future SOAs.
     */
    public function remainingBalance(): float
    {
        return max(0, (float) $this->amount - $this->totalApplied());
    }

    public function getAccountPayments(array $params)
    {
        $authUser = Auth::user();

        return self::with(['user'])
            ->when(
                $authUser && ($authUser->hasRole('broker') || $authUser->hasRole('account_branch_admin')),
                fn ($q) => $q->where('user_id', $authUser->id)
            )
            ->when(
                $authUser && $authUser->hasRole('superadmin'),
                fn ($q) => $q->withTrashed()
            )
            ->when(!empty($params['deposit_date'] ?? null), fn ($q) => $q->whereDate('deposit_date', $params['deposit_date']))
            ->when(
                array_key_exists('mode_of_payment', $params) && $params['mode_of_payment'] !== null && $params['mode_of_payment'] !== '',
                fn ($q) => $q->where('mode_of_payment', (int) $params['mode_of_payment'])
            )
            ->when(
                array_key_exists('status', $params) && $params['status'] !== null && $params['status'] !== '',
                fn ($q) => $q->where('status', (int) $params['status'])
            )
            ->when(!empty($params['created_by'] ?? null), function ($query) use ($params) {
                $query->whereRelation('user', 'username', 'like', '%' . trim((string) $params['created_by']) . '%');
            })
            ->when(!empty($params['remarks'] ?? null), fn ($q) => $q->where('remarks', 'like', '%' . $params['remarks'] . '%'))
            ->latest('id')
            ->paginate($params['per_page'] ?? config('vc.default_pages'));
    }
}
