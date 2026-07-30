<?php

namespace App\Repositories\Dashboard\Concerns;

use App\Models\Soa;
use App\Support\Dashboard\DashboardContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * The slice of `soas` every dashboard read model starts from.
 *
 * Shared by the metric and the per-user read models so there is one definition of "the rows
 * this request is about": the viewer's row-level boundary, the optional account narrowing,
 * and the selected period. Attribution to a single user is deliberately *not* part of it —
 * the per-user report needs the unattributed set to split it per user, while the metrics
 * add {@see Soa::scopeAttributedTo()} on top.
 */
trait BuildsDashboardSoaQueries
{
    /**
     * Rows the viewer may read, narrowed by account but not by period or subject.
     */
    protected function visibleSlice(DashboardContext $context): Builder
    {
        return Soa::query()
            ->visibleTo($context->viewer)
            // Fail closed: a requested user that no longer resolves must not silently widen
            // the report back to everyone.
            ->when(
                $context->hasUnresolvedTarget(),
                static fn (Builder $query) => $query->whereRaw('1 = 0')
            )
            ->when(
                $context->filter->accountCode,
                static fn (Builder $query, string $accountCode) => $query->where('account_code', $accountCode)
            );
    }

    /**
     * Apply the selected bill-date range, if the filter has one at all.
     */
    protected function applyPeriod(Builder $query, DashboardContext $context): Builder
    {
        $filter = $context->filter;

        return $query->when(
            $filter->hasDateRange(),
            static fn (Builder $q) => $q->whereBetween('created_at', [$filter->from, $filter->to])
        );
    }
}
