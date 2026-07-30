<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dashboard\ReportRequest;
use App\Services\Dashboard\DashboardReportService;
use App\Support\Dashboard\DashboardContext;
use App\Support\Dashboard\DashboardFilter;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Inject the report assembler; the metric read models are resolved behind their
     * contracts in {@see \App\Providers\AppServiceProvider}.
     */
    public function __construct(
        private readonly DashboardReportService $reportService,
    ) {
    }

    /**
     * Render the analytics dashboard for the authenticated user.
     *
     * The same action serves the first visit and every filter change: the front end issues
     * an Inertia partial reload with the filter query string, so the URL always describes
     * the slice on screen (shareable and reload-safe) and only the requested props are
     * recomputed. Filters are validated by {@see ReportRequest}; what the viewer is allowed
     * to see is enforced further down by {@see DashboardContext}.
     *
     * @return \Inertia\Response
     */
    public function index(ReportRequest $request)
    {
        $context = DashboardContext::for(
            $request->user(),
            DashboardFilter::fromArray($request->validated()),
        );

        return Inertia::render('Dashboard', $this->reportService->props($context));
    }
}
