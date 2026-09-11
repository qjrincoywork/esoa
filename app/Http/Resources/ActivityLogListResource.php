<?php

namespace App\Http\Resources;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the audit-trail listing.
 *
 * Deliberately lean: the table shows who did what to which record and when, and the
 * stored `properties` blob — which for a created invoice holds every column — is left
 * to {@see ActivityLogDetailResource} so a page of twenty-five entries does not carry
 * twenty-five full snapshots it will not display.
 */
class ActivityLogListResource extends JsonResource
{
    /**
     * Transform the entry into a list row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = collect($this->properties ?? []);

        return [
            'id' => $this->id,
            'logged_at' => CommonHelper::formatDate($this->created_at, true),
            // The orderable value, since the label sorts alphabetically.
            'logged_at_value' => $this->created_at?->toDateTimeString(),

            'log_name' => $this->log_name,
            'module' => AuditLogName::label($this->log_name),

            'event' => $this->event,
            'event_label' => $this->event ? AuditEvent::label($this->event) : null,

            // Already names the record it happened to, e.g. "Billing invoice X was updated".
            'description' => $this->description,

            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,

            'causer' => $this->causer?->username ?: $this->causer?->email,
            'causer_id' => $this->causer_id,

            'batch_uuid' => $this->batch_uuid,
            // How much changed, so a reader can tell a one-field edit from a rewrite
            // without opening it.
            'change_count' => count($properties->get('attributes', [])),
        ];
    }
}
