<?php

namespace App\Models;

use App\Enums\EmailNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One email notification ({@see EmailNotification}) a user is excluded from.
 *
 * Only exclusions are stored: a user receives every notification they have no row for.
 */
class UserNotificationExclusion extends Model
{
    /**
     * Rows inserted per statement: four bindings each, well inside SQL Server's
     * 2,100-parameter limit.
     */
    private const INSERT_CHUNK = 250;

    protected $fillable = [
        'user_id',
        'notification',
    ];

    /**
     * Get the user this exclusion belongs to (belongs-to User via user_id).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Apply the same notification settings to every given user.
     *
     * $settings maps a notification to whether the users should receive it; a
     * notification left out is not touched, so a bulk change can set some notifications
     * and leave the rest as each user has them. Included notifications lose their rows;
     * excluded ones gain a row wherever the user does not already have one. Costs one
     * delete, one read and one insert per chunk, however many users and notifications.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, bool>  $settings
     * @return int How many user/notification settings actually changed.
     */
    public static function apply(array $userIds, array $settings): int
    {
        $include = array_keys(array_filter($settings));
        $exclude = array_keys(array_filter($settings, static fn (bool $receives): bool => !$receives));
        $changed = 0;

        if ($include !== []) {
            $changed += static::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('notification', $include)
                ->delete();
        }

        if ($exclude === []) {
            return $changed;
        }

        $existing = static::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('notification', $exclude)
            ->get(['user_id', 'notification'])
            ->mapWithKeys(static fn (self $row): array => [$row->user_id . '|' . $row->notification => true]);

        $now = now();
        $rows = collect($userIds)
            ->crossJoin($exclude)
            ->reject(static fn (array $pair): bool => isset($existing[$pair[0] . '|' . $pair[1]]))
            ->map(static fn (array $pair): array => [
                'user_id' => $pair[0],
                'notification' => $pair[1],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $rows->chunk(self::INSERT_CHUNK)
            ->each(static fn ($chunk) => static::query()->insert($chunk->values()->all()));

        return $changed + $rows->count();
    }
}
