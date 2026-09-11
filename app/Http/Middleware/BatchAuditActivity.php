<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Activitylog\Facades\LogBatch;
use Symfony\Component\HttpFoundation\Response;

class BatchAuditActivity
{
    /**
     * Tie every audit entry written during one request to a single batch.
     *
     * One user action rarely maps to one model event: creating a concern or a
     * remittance advice writes the record and then writes again once the uploaded file
     * has a path, and a single save can touch several models. Read back one event at a
     * time that looks like unexplained churn; grouped by `batch_uuid` it reads as the
     * one action it was.
     *
     * Doing it here rather than per controller means no write path can forget, and a
     * new one is covered the day it is added.
     *
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        LogBatch::startBatch();

        try {
            return $next($request);
        } finally {
            // Closed even when the request throws, so a failed action cannot leave the
            // batch open and adopt the next one's entries.
            LogBatch::endBatch();
        }
    }
}
