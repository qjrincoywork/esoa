<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * A row of the audit trail.
 *
 * Extends spatie's Activity so the reading side has somewhere of its own to live —
 * the querying the log screen needs does not belong in a vendor class — while writes
 * keep going through the same model (see `config/activitylog.php`), so there is only
 * ever one class representing an entry.
 */
class ActivityLog extends SpatieActivity
{
    /**
     * Get a paginated slice of the audit trail, newest first.
     *
     * Every filter is optional and they compose, so the screen can narrow by module,
     * by kind of change, by who made it and by when, in any combination. The causer is
     * eager-loaded because the list names them on every row; subjects deliberately are
     * not, since the entry's description already carries the record's label and
     * resolving them would mean a query per morph type per page.
     *
     * @param  array<string, mixed>  $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getActivityLogs(array $params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        return self::query()
            ->with('causer:id,username,email')
            ->when(!empty($params['log_name']), fn (Builder $q) => $q->where('log_name', $params['log_name']))
            ->when(!empty($params['event']), fn (Builder $q) => $q->where('event', $params['event']))
            ->when(!empty($params['causer_id']), fn (Builder $q) => $q->where('causer_id', $params['causer_id']))
            ->when(!empty($params['batch_uuid']), fn (Builder $q) => $q->where('batch_uuid', $params['batch_uuid']))
            ->when(!empty($params['subject_type']), fn (Builder $q) => $q->where('subject_type', $params['subject_type']))
            // Dates arrive as calendar days; the "to" bound covers the whole of that day.
            ->when(!empty($params['date_from']), fn (Builder $q) => $q->where(
                'created_at', '>=', Carbon::parse($params['date_from'])->startOfDay()
            ))
            ->when(!empty($params['date_to']), fn (Builder $q) => $q->where(
                'created_at', '<=', Carbon::parse($params['date_to'])->endOfDay()
            ))
            ->when(!empty($params['search_string']), function (Builder $q) use ($params) {
                $search = $params['search_string'];

                $q->where(fn (Builder $inner) => $inner
                    ->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('subject_id', 'LIKE', "%{$search}%")
                    ->orWhereHas('causer', fn (Builder $causer) => $causer
                        ->where('username', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")));
            })
            // id breaks ties so entries written in the same second keep a stable order
            // across pages — without it a row can appear twice or not at all.
            ->orderBy('created_at', OrderType::DESC)
            ->orderBy('id', OrderType::DESC)
            ->paginate($perPage);
    }

    /**
     * The people who actually appear in the trail, for the "who" filter.
     *
     * Built from the distinct causers on record rather than the whole user table, so
     * the filter only ever offers a choice that can return something.
     *
     * @return array<int, array{value: int, name: string}>
     */
    public function causerOptions(): array
    {
        $causerIds = self::query()
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id')
            ->all();

        if ($causerIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $causerIds)
            ->orderBy('username')
            ->get(['id', 'username', 'email'])
            ->map(static fn (User $user): array => [
                'value' => (int) $user->id,
                'name' => $user->username ?: $user->email,
            ])
            ->all();
    }

    /**
     * The other entries written by the same action, oldest first.
     *
     * One action often changes more than one thing; the batch is what ties those
     * entries together, and showing them beside an entry is what makes a single change
     * readable as part of what the person actually did.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function batchSiblings()
    {
        if (!$this->batch_uuid) {
            return self::query()->whereRaw('1 = 0')->get();
        }

        return self::query()
            ->where('batch_uuid', $this->batch_uuid)
            ->whereKeyNot($this->getKey())
            ->orderBy('id', OrderType::ASC)
            ->get();
    }
}
