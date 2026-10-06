<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ActivityLog\ListRequest;
use Illuminate\Support\Arr;

/**
 * Filters for one user's slice of the audit trail (the user pane's Activity tab).
 *
 * Extends the audit-trail listing's request rather than restating it: the tab is the
 * same trail pinned to one person, so it accepts the same filters, messages and
 * audience (superadmin — the trail records what was changed across every module).
 */
class ActivityLogRequest extends ListRequest
{
    /**
     * The audit-trail filters, minus the ones the pinned user already answers.
     *
     * Who the entries belong to comes from the route, and batch/subject narrowing is
     * the detail pane's business, so none of those can be asked for here.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), ['causer_id', 'subject_type', 'batch_uuid']);
    }
}
