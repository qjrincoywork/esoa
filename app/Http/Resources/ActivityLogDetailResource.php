<?php

namespace App\Http\Resources;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Helpers\CommonHelper;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single audit entry, opened up.
 *
 * Turns the stored `properties` blob into the two things a reader actually wants: a
 * field-by-field account of what changed, and the request the change arrived on —
 * including the device it came from, read off the stored user agent. It
 * also says how much else the same action wrote, because one action routinely
 * writes several entries and any one of them read alone is missing its own context.
 */
class ActivityLogDetailResource extends JsonResource
{
    /**
     * Transform the entry into the detail pane's payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = collect($this->properties ?? []);
        $context = (array) $properties->get('context', []);

        return [
            'id' => $this->id,
            'logged_at' => CommonHelper::formatDate($this->created_at, true),
            // Machine-readable, so the pane can also say how long ago it was.
            'logged_at_value' => $this->created_at?->toIso8601String(),

            'log_name' => $this->log_name,
            'module' => AuditLogName::label($this->log_name),
            'records_changes' => AuditLogName::recordsChanges($this->log_name),

            'event' => $this->event,
            'event_label' => $this->event ? AuditEvent::label($this->event) : null,

            'description' => $this->description,

            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,

            'causer' => $this->causer?->username ?: $this->causer?->email,
            'causer_email' => $this->causer?->email,
            'causer_id' => $this->causer_id,

            'batch_uuid' => $this->batch_uuid,

            'changes' => $this->changeRows($properties),
            'context' => $properties->get('context'),
            // The stored agent read back as OS, browser and kind of device; null when
            // the entry came from no request (console or queue) and so has no agent.
            'device' => UserAgent::parse($context['user_agent'] ?? null)?->toArray(),

            // Only the size of the batch travels with the entry; the entries behind it
            // are paged in on demand, since one batch upload writes thousands of them.
            'batch_sibling_count' => $this->resource->batchSiblingCount(),
        ];
    }

    /**
     * Flatten the before/after snapshots into one row per field.
     *
     * The two sides are not always both present — a creation has no "before" and a
     * deletion has no "after" — so the union of their keys drives the rows, and a side
     * that does not apply is simply null rather than an invented blank. A field holding a
     * user's id ({@see CommonHelper::isUserReferenceField()}) shows that user's username,
     * both sides resolved in one query.
     *
     * @param  \Illuminate\Support\Collection<string, mixed>  $properties
     * @return array<int, array{field: string, label: string, from: string|null, to: string|null}>
     */
    private function changeRows($properties): array
    {
        $after = (array) $properties->get('attributes', []);
        $before = (array) $properties->get('old', []);

        $fields = array_keys($after + $before);
        sort($fields);

        CommonHelper::primeUsernames(CommonHelper::userIdsIn([$before, $after]));

        return array_map(fn (string $field): array => [
            'field' => $field,
            'label' => $this->fieldLabel($field),
            'from' => array_key_exists($field, $before) ? $this->readable($field, $before[$field]) : null,
            'to' => array_key_exists($field, $after) ? $this->readable($field, $after[$field]) : null,
        ], $fields);
    }

    /**
     * A field's name as a reader would say it. A user reference drops its "_id", since
     * the row shows the user rather than the id: `user_id` reads "User".
     */
    private function fieldLabel(string $field): string
    {
        $name = CommonHelper::isUserReferenceField($field) ? preg_replace('/_id$/', '', $field) : $field;

        return str_replace('_', ' ', ucfirst($name));
    }

    /**
     * Render a stored value as something displayable.
     *
     * Audited columns are not all scalars — a billing reference is cast to an array —
     * and a blank is shown as an em dash so "set to empty" is not mistaken for
     * "unchanged". A user's id is shown as their username.
     *
     * @param  mixed  $value
     */
    private function readable(string $field, $value): ?string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (CommonHelper::isUserReferenceField($field) && is_numeric($value)) {
            return CommonHelper::usernameFor($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
