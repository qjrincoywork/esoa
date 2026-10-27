<?php

namespace App\Models;

use App\Enums\{AdvisoryState, AuditLogName};
use App\Support\LogsAuditActivity;
use Illuminate\Database\Eloquent\{
    Builder,
    Model,
    SoftDeletes,
    Relations\BelongsTo
};

/**
 * A notice shown to every user right after they sign in — a service advisory, a
 * maintenance window — managed from the Advisories module rather than shipped in code.
 *
 * Whether it is shown is decided by two things together: the switch (`is_active`) and
 * the window (`starts_at`..`ends_at`, either end open). See {@see scopeLive()}.
 */
class Advisory extends Model
{
    use SoftDeletes, LogsAuditActivity;

    /**
     * Write this model's audit trail to the advisory channel.
     */
    public function auditLogName(): string
    {
        return AuditLogName::ADVISORY;
    }

    /**
     * An advisory is recognised by its title.
     */
    protected function auditSubjectLabel(): string
    {
        return (string) ($this->title ?: '#'.$this->getKey());
    }

    /**
     * Who last touched the row is already the causer of the audit entry.
     *
     * @return array<int, string>
     */
    protected function auditExcludedAttributes(): array
    {
        return ['created_by', 'updated_by'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'message',
        'image',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user who created this advisory.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this advisory.
     *
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Advisories that are switched on and whose window contains the current moment.
     * A missing start or end leaves that side of the window open.
     */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', 1)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Where this advisory stands right now — the same rule as {@see scopeLive()}, for
     * one row already in memory. A disabled advisory reads as Disabled whatever its
     * dates, since the switch is what an administrator reached for.
     */
    public function state(): int
    {
        $now = now();

        return match (true) {
            !$this->is_active => AdvisoryState::DISABLED,
            $this->starts_at !== null && $this->starts_at->gt($now) => AdvisoryState::SCHEDULED,
            $this->ends_at !== null && $this->ends_at->lt($now) => AdvisoryState::EXPIRED,
            default => AdvisoryState::LIVE,
        };
    }

    /**
     * Whether this advisory is being shown to users right now.
     */
    public function isLive(): bool
    {
        return $this->state() === AdvisoryState::LIVE;
    }

    /**
     * Get a paginated list of advisories for the admin listing, newest first.
     *
     * Soft-deleted rows are included so they can be restored. Optionally filters by
     * title and by state; the state is derived from the clock, so each one is
     * translated back into the column conditions that produce it.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAdvisories($params)
    {
        $now = now();

        return self::with(['creator', 'updater'])
            ->withTrashed()
            ->when(!empty($params['title'] ?? null), function ($query) use ($params) {
                $query->where('title', 'like', '%' . $params['title'] . '%');
            })
            ->when(!empty($params['state'] ?? null), function ($query) use ($params, $now) {
                match ((int) $params['state']) {
                    AdvisoryState::LIVE => $query->live(),
                    AdvisoryState::SCHEDULED => $query->where('is_active', 1)->where('starts_at', '>', $now),
                    AdvisoryState::EXPIRED => $query->where('is_active', 1)
                        ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where('ends_at', '<', $now),
                    AdvisoryState::DISABLED => $query->where('is_active', 0),
                };
            })
            ->latest('id')
            ->paginate($params['per_page']);
    }
}
