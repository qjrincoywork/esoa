import { dashboard } from '@/routes';
import type { DashboardFilters } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/** Props recomputed on a filter change; `filter_options` is deliberately left out. */
const RELOADABLE_PROPS = [
    'filters',
    'data_window',
    'summary',
    'aging_buckets',
    'status_buckets',
    'billing_trend',
    'top_accounts',
    'user_reports',
] as const;

/** Sentinel for "no user selected" — reka-ui inputs cannot carry an empty value. */
export const ALL_USERS = 0;

/**
 * Unbounded default, mirroring `DashboardFilter::DEFAULT_PRESET`. The landing page must
 * count every invoice the viewer can see so its figures agree with the SOA dashboard.
 */
export const DEFAULT_PRESET = 'all_time';

export interface DashboardFilterPatch {
    preset?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    user_id?: number | null;
    account_code?: string | null;
}

/**
 * Filter state and reload behavior of the analytics dashboard.
 *
 * One filter row scopes every widget on the page, so applying a filter is a single Inertia
 * partial reload rather than a per-chart fetch: the numbers can never disagree with each
 * other, the URL always describes the slice on screen (shareable, reload-safe), and the
 * option lists are excluded from the reload so choosing a user never re-queries the user
 * list. `processing` lets the charts hold their previous render at reduced opacity — no
 * skeleton flash, no layout jump.
 */
export function useDashboardFilters(current: () => DashboardFilters) {
    const processing = ref(false);

    /** Custom mode is implied by the server: an explicit range wins over any preset. */
    const isCustomRange = computed(() => current().preset === 'custom');

    /**
     * Merge a patch over the active filters and reload.
     *
     * A named preset and an explicit range are mutually exclusive — sending both would let
     * the server resolve one and silently drop the other, so only the winning pair is sent.
     */
    const apply = (patch: DashboardFilterPatch): void => {
        const active = current();
        const merged: DashboardFilterPatch = {
            preset: active.preset,
            date_from: active.date_from,
            date_to: active.date_to,
            user_id: active.user_id,
            account_code: active.account_code,
            ...patch,
        };

        const usesCustomRange =
            patch.date_from !== undefined || patch.date_to !== undefined
                ? Boolean(merged.date_from && merged.date_to)
                : isCustomRange.value;

        const params: Record<string, string | number> = {};

        if (usesCustomRange && merged.date_from && merged.date_to) {
            params.date_from = merged.date_from;
            params.date_to = merged.date_to;
        } else if (merged.preset && merged.preset !== 'custom') {
            params.preset = merged.preset;
        }

        if (merged.user_id) {
            params.user_id = merged.user_id;
        }

        if (merged.account_code) {
            params.account_code = merged.account_code;
        }

        router.get(dashboard().url, params, {
            only: [...RELOADABLE_PROPS],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                processing.value = true;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    };

    /** Back to the default slice: default preset, no user and no account narrowing. */
    const reset = (): void => {
        router.get(
            dashboard().url,
            {},
            {
                only: [...RELOADABLE_PROPS],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => {
                    processing.value = true;
                },
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    };

    /** True when anything narrows the default slice (drives the "Reset" affordance). */
    const isFiltered = computed(() => {
        const active = current();

        return (
            active.user_id !== null ||
            active.account_code !== null ||
            active.preset !== DEFAULT_PRESET
        );
    });

    return { processing, isCustomRange, isFiltered, apply, reset };
}
