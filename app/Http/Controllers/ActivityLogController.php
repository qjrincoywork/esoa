<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Http\Requests\ActivityLog\ListRequest;
use App\Http\Resources\ActivityLogDetailResource;
use App\Http\Resources\ActivityLogListResource;
use App\Http\Resources\CommonResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    /**
     * ActivityLog model instance.
     *
     * @var ActivityLog
     */
    protected $activityLog;

    /**
     * ActivityLogController constructor.
     *
     * @return void
     */
    public function __construct(ActivityLog $activityLog)
    {
        $this->activityLog = $activityLog;
    }

    /**
     * Render the Inertia "activity_logs/Index" page with a filtered audit trail.
     *
     * Read-only by design: the trail is evidence of what happened, so this module
     * offers no way to edit or remove an entry — retention is handled on a schedule
     * (`activitylog:clean`) rather than by hand.
     *
     * The filter options travel with the page: modules and events come from the enums
     * that define what can be written, and the causer list from who actually appears
     * in the trail, so no filter offers a choice that returns nothing.
     * Filters are validated by {@see ListRequest}.
     *
     * Access control (RBAC): the route sits behind the superadmin role group, and
     * {@see ListRequest::authorize()} states the same audience independently.
     *
     * @return \Inertia\Response
     */
    public function index(ListRequest $request)
    {
        $logs = $this->activityLog->getActivityLogs($request->validated());

        return Inertia::render('activity_logs/Index', [
            'activity_logs' => new CommonResource(ActivityLogListResource::collection($logs)),
            'filter_options' => [
                'modules' => AuditLogName::list(),
                'events' => AuditEvent::list(),
                'causers' => $this->activityLog->causerOptions(),
            ],
        ]);
    }

    /**
     * Return one audit entry with its field-by-field changes (AJAX only).
     *
     * Feeds the detail pane: the listing stays lean and the stored snapshots are
     * fetched only for the entry actually opened. Non-AJAX requests fall through and
     * receive no content.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function show(int $id, Request $request)
    {
        $log = $this->activityLog
            ->with('causer:id,username,email')
            ->findOrFail($id);

        // Return JSON for AJAX requests (no URL change)
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'activity_log' => new ActivityLogDetailResource($log),
            ]);
        }
    }

    /**
     * Return one page of the rest of the batch an entry belongs to (AJAX only).
     *
     * The batch is the action an entry was part of, and a batch upload writes one
     * entry per row of the file — so it is paged from here rather than carried by the
     * detail, which would otherwise ship thousands of rows to show ten.
     *
     * The batch is taken from the entry itself, never from the request, so a reader
     * can only ever page through the action they already have in front of them.
     * Filters are validated by {@see ListRequest}, which also states the audience.
     *
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function batchSiblings(int $id, ListRequest $request)
    {
        $log = $this->activityLog->findOrFail($id);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'batch_siblings' => new CommonResource(
                    ActivityLogListResource::collection($log->getBatchSiblings($request->validated()))
                ),
            ]);
        }
    }
}
