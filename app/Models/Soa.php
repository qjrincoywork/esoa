<?php

namespace App\Models;

use App\Enums\OrderType;
use App\Enums\Server;
use App\Enums\SoaAging;
use App\Enums\SoaStatus;
use App\Helpers\SqlDatabase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\{
    Model,
    SoftDeletes,
    Relations\HasMany,
    Relations\HasOne,
    Relations\BelongsTo,
    Relations\BelongsToMany
};

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
        'account_type',
        'account_code',
        'branch_code',
        'billing_ref',
        'billing_ref_from',
        'bill_type',
        'status',
        'due_date',
        'period_date_from',
        'period_date_to',
        'contract_date_from',
        'contract_date_to',
        'amount',
        'file_pdf',
        'file_xls',
    ];

    protected $casts = [
        'billing_ref' => 'array',
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

    public function concerns(): BelongsToMany
    {
        return $this->belongsToMany(
            Concern::class,
            'soa_concerns',
            'soa_id',
            'concern_id'
        );
    }

    public function accountPayments(): BelongsToMany
    {
        return $this->belongsToMany(
            AccountPayment::class,
            'soa_account_payments',
            'soa_id',
            'account_payment_id'
        );
    }

    /**
     * Persist a single audit row for this SOA. Reusable from any feature (amount, status, files, etc.).
     *
     * @param  array{from?: array<string, mixed>|null, to: array<string, mixed>}  $snapshot
     */
    public function recordActivity(string $event, array $snapshot, ?User $actor = null): SoaActivity
    {
        $event = trim($event);
        if ($event === '' || strlen($event) > 191) {
            throw new \InvalidArgumentException('Invalid SOA activity event.');
        }
        if (!isset($snapshot['to']) || ! is_array($snapshot['to'])) {
            throw new \InvalidArgumentException('SOA activity snapshot must include a "to" array.');
        }
        $from = $snapshot['from'] ?? null;
        if ($from !== null && ! is_array($from)) {
            throw new \InvalidArgumentException('SOA activity "from" must be an array or null.');
        }

        $user = $actor ?? auth()->user();
        if (!$user instanceof User) {
            throw new \RuntimeException('SOA activity requires an authenticated user.');
        }

        $name = $user->username ?? $user->email ?? (string) $user->id;

        return $this->soaActivity()->create([
            'user_id' => (int) $user->getAuthIdentifier(),
            'name' => $name,
            'event' => $event,
            'from' => $from,
            'to' => $snapshot['to'],
        ]);
    }

    /**
     * Run a callback inside a DB transaction, then record an activity in the same transaction.
     *
     * @param  callable(self): mixed  $callback
     * @param  array{from?: array<string, mixed>|null, to: array<string, mixed>}  $snapshot
     */
    public function runInTransactionWithActivity(callable $callback, string $event, array $snapshot, ?User $actor = null): mixed
    {
        return DB::transaction(function () use ($callback, $event, $snapshot, $actor) {
            $result = $callback($this);
            $this->recordActivity($event, $snapshot, $actor);

            return $result;
        });
    }

    /**
     * Base query for SOA list and export (shared filters and role scoping).
     */
    public function listQuery(array $params): Builder
    {
        $authUser = auth()->user();

        $query = self::query()
            ->when(!empty($params), function ($query) use ($params) {
                $query->tap(fn (Builder $q) => $this->applyListSearchFilters($q, $params));
                $query->tap(fn (Builder $q) => $this->applyListSearchFiltersDueIn($q, $params));
                $query->tap(fn (Builder $q) => $this->applyListDateFilters($q, $params));
            })
            ->when($authUser->hasRole('broker'), function ($query) use ($authUser) {
                $agentAccounts = (new SqlDatabase(Server::HMS))
                    ->getAccountsOfAgent($authUser->userDetail?->agent_code ?? null);
                $query->whereIn('account_code', $agentAccounts);
            })
            ->when($authUser->hasAnyRole(['account_branch_admin', 'group_account_admin']), function ($query) use ($authUser) {
                $this->applyUserAccountRestriction($query, $authUser);
            })
            ->orderBy('created_at', OrderType::DESC);

        if ($authUser && $authUser->hasAnyRole(['superadmin', 'admin'])) {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Retrieves a paginated list of SOA records from the database.
     *
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getSoas($params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        return $this->listQuery($params)->paginate($perPage);
    }

    /**
     * Past-due aging counts keyed by {@see SoaAging} values. Single aggregate query (no per-bucket counts).
     *
     * @return array<int, int>
     */
    public function agingCountsPastDue(): array
    {
        $values = SoaAging::getValues();
        $statusValues = [SoaStatus::ENDORSED, SoaStatus::DISPUTED];
        $values = array_merge($values, $statusValues); // Include endorsed/disputed buckets in aging counts
        $authUser = auth()->user();
        if (!$authUser) {
            return array_fill_keys($values, 0);
        }
        $result = [];
        foreach ($values as $soaAging) {
            $result[] = [
                'value' => $soaAging,
                'count' => self::query()
                    ->tap(fn (Builder $q) => $this->applyUserAccountRestriction($q, $authUser))
                    ->tap(fn (Builder $q) => $this->applyListSearchFiltersDueIn($q, in_array($soaAging, $statusValues) ? ['status' => $soaAging] : ['due_in' => $soaAging]))
                    ->where('status', '!=', SoaStatus::PAID)
                    ->count(),
            ];
        }

        return $result;
    }

    /**
     * Restrict the query to SOAs the authenticated user is allowed to see based on their role.
     * - account_branch_admin: limited to their single account (and branch if set).
     * - group_account_admin:  limited to all account/branch pairs in their user_accounts.
     * Other roles are unrestricted (handled separately, e.g. broker).
     */
    protected function applyUserAccountRestriction(Builder $query, ?User $authUser): void
    {
        if (!$authUser) {
            return;
        }

        if ($authUser->hasRole('account_branch_admin')) {
            $firstAccount = $authUser->userAccounts->first();
            $query->where('account_code', $firstAccount?->account_code ?? null);
            if (!empty($firstAccount?->branch_code)) {
                $query->where('branch_code', $firstAccount->branch_code);
            }
            return;
        }

        if ($authUser->hasRole('group_account_admin')) {
            $userAccounts = $authUser->userAccounts;
            if ($userAccounts->isEmpty()) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->where(function (Builder $q) use ($userAccounts) {
                foreach ($userAccounts as $ua) {
                    $q->orWhere(function (Builder $sub) use ($ua) {
                        $sub->where('account_code', $ua->account_code);
                        if (!empty($ua->branch_code)) {
                            $sub->where('branch_code', $ua->branch_code);
                        }
                    });
                }
            });
        }
    }

    /**
     * Apply validated list filters (aligned with SavingSoaForm: account type, codes, billing ref, SOA number, status).
     */
    protected function applyListSearchFilters(Builder $query, array $params): void
    {
        if (!empty($params['soanum'] ?? null)) {
            $query->where('soa_number', 'LIKE', '%' . $params['soanum'] . '%');
        }
        if (!empty($params['account_code'] ?? null)) {
            $query->where('account_code', $params['account_code']);
        }
        if (!empty($params['branch_code'] ?? null)) {
            $query->where('branch_code', $params['branch_code']);
        }
        if (!empty($params['billing_ref'] ?? null)) {
            $query->where('billing_ref', $params['billing_ref']);
        }
        if (!empty($params['account_type'] ?? null)) {
            $query->where('account_type', $params['account_type']);
        }
        if (array_key_exists('status', $params) && $params['status'] !== null && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (array_key_exists('bill_type', $params) && $params['bill_type'] !== null && $params['bill_type'] !== '') {
            $query->where('bill_type', (int) $params['bill_type']);
        }
    }

    /**
     * Apply due-date and bill-date (created_at) range filters for list and export.
     */
    protected function applyListDateFilters(Builder $query, array $params): void
    {
        if (!empty($params['due_date_from'] ?? null)) {
            $query->where(
                'due_date',
                '>=',
                Carbon::parse($params['due_date_from'])->startOfDay()
            );
        }

        if (!empty($params['due_date_to'] ?? null)) {
            $query->where(
                'due_date',
                '<=',
                Carbon::parse($params['due_date_to'])->endOfDay()
            );
        }

        if (!empty($params['bill_date_from'] ?? null)) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($params['bill_date_from'])->startOfDay()
            );
        }

        if (!empty($params['bill_date_to'] ?? null)) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($params['bill_date_to'])->endOfDay()
            );
        }
    }

    /**
     * Apply validated list filters (aligned with SoaAging: due in aging).
     */
    protected function applyListSearchFiltersDueIn(Builder $query, array $params): void
    {
        if (array_key_exists('due_in', $params) && $params['due_in'] !== null && $params['due_in'] !== '') {
            $query->when($params['due_in'] == SoaAging::PAST_DUE, function ($query) use ($params) {
                $range = SoaAging::pastDueDayBucketsRange($params['due_in']);
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) < ?',
                    [end($range) ?? 0]
                );
            })
            ->when($params['due_in'] == SoaAging::DUE_WITHIN_30_DAYS, function ($query) use ($params) {
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?',
                    SoaAging::pastDueDayBucketsRange($params['due_in'])
                );
            })
            ->when($params['due_in'] == SoaAging::DUE_WITHIN_60_DAYS, function ($query) use ($params) {
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?',
                    SoaAging::pastDueDayBucketsRange($params['due_in'])
                );
            })
            ->when($params['due_in'] == SoaAging::DUE_WITHIN_90_DAYS, function ($query) use ($params) {
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?',
                    SoaAging::pastDueDayBucketsRange($params['due_in'])
                );
            })
            ->when($params['due_in'] == SoaAging::DUE_WITHIN_120_DAYS, function ($query) use ($params) {
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) BETWEEN ? AND ?',
                    SoaAging::pastDueDayBucketsRange($params['due_in'])
                );
            })
            ->when($params['due_in'] == SoaAging::DUE_WITHIN_MORE_THAN_120_DAYS, function ($query) use ($params) {
                $range = SoaAging::pastDueDayBucketsRange($params['due_in']);
                $query->whereRaw(
                    'DATEDIFF(day, GETDATE(), due_date) > ?',
                    [reset($range) ?? 0]
                );
            });
        }

        if (array_key_exists('status', $params) && $params['status'] !== null && $params['status'] !== '') {
            $query->when($params['status'] == SoaStatus::ENDORSED, function ($query) use ($params) {
                $query->where('status', SoaStatus::ENDORSED);
            })
            ->when($params['status'] == SoaStatus::DISPUTED, function ($query) use ($params) {
                $query->where('status', SoaStatus::DISPUTED);
            });
        }
    }

    public function saveSoa(array $data) {
        // Handle multiple billing_refs (JSON array from form)
        if (isset($data['billing_ref']) && is_string($data['billing_ref'])) {
            $decoded = json_decode($data['billing_ref'], true);
            if (is_array($decoded)) {
                $data['billing_ref'] = implode(',', $decoded);
            }
        }

        if (isset($data['id'])) {
            $soa = self::find($data['id']);
            $soa->update($data);
        } else {
            $soa = self::create($data);
        }

        return $soa;
    }
}
