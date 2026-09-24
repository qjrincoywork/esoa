<?php

namespace App\Models;

use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * @param  array<string, mixed>  $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getActivityLogs(array $params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        return $this->filtered($params)
            // id breaks ties so entries written in the same second keep a stable order
            // across pages — without it a row can appear twice or not at all.
            ->orderBy('created_at', OrderType::DESC)
            ->orderBy('id', OrderType::DESC)
            // The page is taken from the params rather than left to be resolved from
            // the query string, so the slice returned is the one that was asked for.
            ->paginate($perPage, ['*'], 'page', $params['page'] ?? null);
    }

    /**
     * The other entries written by the same action, oldest first, a page at a time.
     *
     * One action often changes more than one thing; the batch is what ties those
     * entries together, and showing them beside an entry is what makes a single change
     * readable as part of what the person actually did. It is paged rather than
     * returned whole because one batch upload writes an entry per row of the file —
     * thousands of them — and a reader only ever looks at a screenful.
     *
     * The same filters as the listing apply, so a large batch can still be narrowed.
     *
     * @param  array<string, mixed>  $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getBatchSiblings(array $params)
    {
        $perPage = $params['per_page'] ?? config('vc.default_pages');

        // An entry written outside a batch has no siblings: answer with an empty page
        // rather than with a query that cannot match.
        if (!$this->batch_uuid) {
            return new LengthAwarePaginator([], 0, $perPage, $params['page'] ?? 1);
        }

        return $this->filtered(array_merge($params, [
            'batch_uuid' => $this->batch_uuid,
            'exclude_id' => $this->getKey(),
        ]))
            // Oldest first: the batch reads as the sequence the action wrote it in.
            ->orderBy('id', OrderType::ASC)
            ->paginate($perPage, ['*'], 'page', $params['page'] ?? null);
    }

    /**
     * How many other entries the same action wrote.
     *
     * The detail pane needs the size of the batch before it needs the batch itself —
     * to label the tab and to know whether there is anything behind it — and a count
     * costs one cheap query instead of carrying rows nobody has asked to see.
     */
    public function batchSiblingCount(): int
    {
        if (!$this->batch_uuid) {
            return 0;
        }

        return self::query()
            ->where('batch_uuid', $this->batch_uuid)
            ->whereKeyNot($this->getKey())
            ->count();
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
     * The trail narrowed by whichever filters were asked for.
     *
     * Every filter is optional and they compose, so a caller can narrow by module, by
     * kind of change, by who made it and by when, in any combination. Shared by the
     * listing and by the batch of a single action, which read the same table through
     * the same filters and differ only in ordering.
     *
     * The causer is eager-loaded because every row names them; subjects deliberately
     * are not, since the entry's description already carries the record's label and
     * resolving them would mean a query per morph type per page.
     *
     * @param  array<string, mixed>  $params
     */
    private function filtered(array $params): Builder
    {
        return self::query()
            ->with('causer:id,username,email')
            ->when(!empty($params['log_name']), fn (Builder $q) => $q->where('log_name', $params['log_name']))
            ->when(!empty($params['event']), fn (Builder $q) => $q->where('event', $params['event']))
            ->when(!empty($params['causer_id']), fn (Builder $q) => $q->where('causer_id', $params['causer_id']))
            ->when(!empty($params['batch_uuid']), fn (Builder $q) => $q->where('batch_uuid', $params['batch_uuid']))
            ->when(!empty($params['subject_type']), fn (Builder $q) => $q->where('subject_type', $params['subject_type']))
            // Reading one entry's batch means every entry but that one.
            ->when(!empty($params['exclude_id']), fn (Builder $q) => $q->whereKeyNot($params['exclude_id']))
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
            });
    }
}
