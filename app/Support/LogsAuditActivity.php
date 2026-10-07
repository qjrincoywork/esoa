<?php

namespace App\Support;

use App\Enums\AuditLogName;
use Illuminate\Http\Request;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Audit trail for a module's records, on top of spatie/laravel-activitylog.
 *
 * The policy lives here rather than in each model so the three audited modules cannot
 * drift apart in what they record — a trail is only worth reading if every module
 * answers "who changed what, when, and from where" the same way. A model opts in by
 * using this trait and naming its channel; everything else has a working default:
 *
 *  - the audited attributes are the model's own `$fillable`, so a column added to a
 *    module is audited without anyone remembering to update a second list;
 *  - only actual changes are stored, and an update that changed nothing is dropped;
 *  - each entry gets a sentence a person can read, and the request context behind it.
 *
 * Override {@see auditExcludedAttributes()} to keep a column out of the trail, and
 * {@see auditSubjectLabel()} to say how the record is named in that sentence.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait LogsAuditActivity
{
    use LogsActivity;

    /**
     * The audit channel this model writes to.
     *
     * @return string One of the {@see AuditLogName} values.
     */
    abstract public function auditLogName(): string;

    /**
     * The logging policy spatie applies to every event on this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->auditLogName())
            ->logOnly($this->auditedAttributes())
            // A trail of "changed nothing" entries buries the changes that matter.
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->auditDescription($eventName));
    }

    /**
     * Which attributes are worth recording.
     *
     * Defaults to everything the model accepts as input, minus what it excludes: the
     * fields a request can set are exactly the fields a person can change.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return array_values(array_diff($this->getFillable(), $this->auditExcludedAttributes()));
    }

    /**
     * Attributes to keep out of the trail — noisy, derived, or sensitive columns.
     *
     * @return array<int, string>
     */
    protected function auditExcludedAttributes(): array
    {
        return [];
    }

    /**
     * How this record is named in the trail, for a reader who cannot look up an id.
     */
    protected function auditSubjectLabel(): string
    {
        return '#'.$this->getKey();
    }

    /**
     * One readable sentence per entry, e.g. "Billing invoice BI-0001 was updated".
     */
    protected function auditDescription(string $eventName): string
    {
        return sprintf(
            '%s %s was %s',
            AuditLogName::label($this->auditLogName()),
            $this->auditSubjectLabel(),
            $eventName
        );
    }

    /**
     * Stamp the request behind the change onto the entry.
     *
     * Spatie already records the causer; what it cannot know is where the change came
     * from, which is the difference between "this user changed it" and "this user
     * changed it from this address, through this route". Console and queue work has no
     * request, so the context is simply absent there rather than faked.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        // Console and queue work is not a request; there is nothing truthful to stamp.
        if (app()->runningInConsole()) {
            return;
        }

        $context = $this->auditRequestContext(request());

        if ($context === []) {
            return;
        }

        $activity->properties = $activity->properties->put('context', $context);
    }

    /**
     * The parts of a request worth keeping beside the change.
     *
     * Separate from {@see tapActivity()} so it can be exercised with any request
     * rather than only inside a live one. The shape itself lives in
     * {@see AuditContext}, which entries not tied to a model event share.
     *
     * @return array<string, string>
     */
    protected function auditRequestContext(Request $request): array
    {
        return AuditContext::forRequest($request);
    }
}
