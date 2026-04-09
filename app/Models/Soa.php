<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
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
        'account_type',
        'account_code',
        'branch_code',
        'billing_ref',
        'bill_type',
        'status',
        'due_date',
        'period_date_from',
        'period_date_to',
        'amount',
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
     * Retrieves a paginated list of SOA records from the database.
     *
     * @param array $params
     * @return \Illuminate\Pagination\Paginator
     */
    public function getSoas($params)
    {
        $authUser = auth()->user();
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        $result = self::query()
            ->tap(fn (Builder $q) => $this->applyListSearchFilters($q, $params))
            ->when(isset($authUser->userDetail->account_code), function ($query) use ($authUser) {
                $query->where('account_code', $authUser->userDetail->account_code);
            })
            ->when(isset($authUser->userDetail->branch_code), function ($query) use ($authUser) {
                $query->where('branch_code', $authUser->userDetail->branch_code);
            })
            ->orderBy('id', OrderType::DESC);

        if ($authUser && $authUser->hasRole('superadmin')) {
            $result->withTrashed();
        }

        return $result->paginate($perPage);
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
        // if (!empty($params['account_type'] ?? null)) {
        //     $type = $params['account_type'];
        //     $query->whereExists(function ($sub) use ($type) {
        //         $sub->select(DB::raw(1))
        //             ->from('accounts')
        //             ->whereColumn('accounts.code', 'soas.account_code')
        //             ->where('accounts.account_type', $type)
        //             ->whereNull('accounts.deleted_at');
        //     });
        // }
        if (array_key_exists('status', $params) && $params['status'] !== null && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
    }

    public function saveSoa(array $data) {
        if (isset($data['id'])) {
            $soa = self::find($data['id']);
            $soa->update($data);
        } else {
            $soa = self::create($data);
        }
    }
}
