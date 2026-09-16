<script setup lang="ts">
/**
 * The audit trail: who changed what, in which module, and when.
 *
 * Read-only by design — there is no create, edit or delete here, because the value of
 * a trail is that it cannot be edited. Navigation is all filtering and reading: narrow
 * by module, event, person or date, then open a row to see the field-by-field changes.
 */
import { ref, watch, computed, h } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import RightPane from '@/components/RightPane.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectItem, SelectValue } from '@/components/ui/select';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { useActivityLogs, type ActivityLogRow } from '@/composables/activityLogs';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { SlidersHorizontal, X } from 'lucide-vue-next';

type LogsPagination = {
    current_page: number
    per_page: number
    total: number
    data: unknown[]
}

type Option = { value: string | number; name: string }

const page = usePage();
const { slug } = useModulePermissions();

const {
    openActivityLog,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
} = useActivityLogs();

const logs = computed<LogsPagination>(() => {
    const props = (page.props as any).activity_logs as LogsPagination | undefined;

    return props ?? { current_page: 1, per_page: 10, total: 0, data: [] };
});

const filterOptions = computed(() => {
    const opts = (page.props as any).filter_options as
        | { modules?: Option[]; events?: Option[]; causers?: Option[] }
        | undefined;

    return {
        modules: opts?.modules ?? [],
        events: opts?.events ?? [],
        causers: opts?.causers ?? [],
    };
});

const columnHelper = createColumnHelper();
const pagination = ref({
    current_page: logs.value.current_page,
    per_page: Number(logs.value.per_page),
    total: logs.value.total,
});

const searchQuery = ref('');
const hasInitialized = ref(false);

// --- Filters ---
const FILTER_ALL = 'all'; // sentinel — means "no filter applied"

const filters = ref({ log_name: '', event: '', causer_id: '', date_from: '', date_to: '' });

const filtersActive = computed(() => Object.values(filters.value).some((value) => value !== ''));

/** Selects bind to a sentinel rather than '' so "All" is a real, selectable option. */
const asSelectModel = (key: 'log_name' | 'event' | 'causer_id') => computed({
    get: () => filters.value[key] || FILTER_ALL,
    set: (v: string | undefined) => { filters.value[key] = v === FILTER_ALL ? '' : (v ?? ''); },
});

const moduleModel = asSelectModel('log_name');
const eventModel = asSelectModel('event');
const causerModel = asSelectModel('causer_id');

const clearFilters = () => {
    filters.value = { log_name: '', event: '', causer_id: '', date_from: '', date_to: '' };
};

const EVENT_CLASSES: Record<string, string> = {
    created: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    deleted: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    restored: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
};

/** A small pill, so the kind of change is readable at a glance down the column. */
const badge = (text: string, classes: string) =>
    h('span', { class: ['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', classes] }, text);

const columns: any[] = [
    columnHelper.accessor('logged_at', {
        header: 'When',
        // The label reads well but sorts alphabetically; sort on the timestamp instead.
        sortingFn: (a: any, b: any) =>
            String(a.original?.logged_at_value ?? '').localeCompare(String(b.original?.logged_at_value ?? '')),
        cell: (info: any) => info.getValue() ?? '—',
    }),
    columnHelper.accessor('module', {
        header: 'Module',
        cell: (info: any) => badge(info.getValue() ?? '—', 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300'),
    }),
    columnHelper.accessor('event_label', {
        header: 'Event',
        cell: (info: any) => badge(
            info.getValue() ?? '—',
            EVENT_CLASSES[info.row.original?.event ?? ''] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
        ),
    }),
    columnHelper.accessor('description', {
        header: 'Activity',
        cell: (info: any) => info.getValue() ?? '—',
    }),
    columnHelper.accessor('causer', {
        header: 'Changed by',
        cell: (info: any) => info.getValue() ?? 'System',
    }),
    columnHelper.accessor('change_count', {
        header: 'Fields',
        cell: (info: any) => Number(info.getValue()) || '—',
    }),
];

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Activity Logs',
        href: slug.value,
    },
];

const fetchLogs = () => {
    const params: Record<string, any> = {
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
    };

    if (searchQuery.value.trim()) params.search_string = searchQuery.value.trim();
    if (filters.value.log_name) params.log_name = filters.value.log_name;
    if (filters.value.event) params.event = filters.value.event;
    if (filters.value.causer_id) params.causer_id = filters.value.causer_id;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;

    router.get(`/${slug.value}`, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['activity_logs'],
    });
};

// Debounced fetch for the search box
const searchTimeout = ref<number | null>(null);
watch(searchQuery, () => {
    if (!hasInitialized.value) return;
    if (searchTimeout.value) clearTimeout(searchTimeout.value);

    searchTimeout.value = window.setTimeout(() => {
        pagination.value.current_page = 1;
        fetchLogs();
    }, 500);
});

// Keep local pagination in step with what the server returned
const isUpdatingFromServer = ref(false);
watch(logs, (next) => {
    if (!next) return;
    isUpdatingFromServer.value = true;
    pagination.value.current_page = next.current_page;
    pagination.value.per_page = Number(next.per_page);
    pagination.value.total = next.total;

    setTimeout(() => { isUpdatingFromServer.value = false; }, 300);
});

// Debounced fetch for paging
const fetchTimeout = ref<number | null>(null);
watch(
    () => [pagination.value.current_page, pagination.value.per_page],
    ([currentPage, perPage]) => {
        if (!hasInitialized.value || isUpdatingFromServer.value) return;
        if (fetchTimeout.value) clearTimeout(fetchTimeout.value);

        fetchTimeout.value = window.setTimeout(() => {
            pagination.value.current_page = Number(currentPage) || 1;
            pagination.value.per_page = Number(perPage) || 10;
            fetchLogs();
        }, 50);
    },
);

// Debounced fetch when any filter changes
const filterTimeout = ref<number | null>(null);
watch(filters, () => {
    if (filterTimeout.value) clearTimeout(filterTimeout.value);

    filterTimeout.value = window.setTimeout(() => {
        pagination.value.current_page = 1;
        hasInitialized.value = true;
        fetchLogs();
    }, 300);
}, { deep: true });

const openEntry = (row: ActivityLogRow) => openActivityLog(row);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Activity Logs" />
        <div class="bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-6">
            <div class="flex flex-col gap-3 mb-4">
                <!-- Top row: what this is + search -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <p class="text-sm text-[var(--color-text-muted)]">
                        A read-only record of changes to billing invoices, concerns and remittance advices.
                        Select a row to see what changed.
                    </p>
                    <div class="relative w-full sm:w-72">
                        <label class="sr-only" for="activity-search">Search activity</label>
                        <input
                            id="activity-search"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search record or person..."
                            class="border border-[var(--color-border-strong)] rounded-md text-sm bg-[var(--color-surface)] text-[var(--color-text)] focus:ring-2 focus:ring-opacity-50 focus:border-transparent w-full px-4 py-2 pr-8"
                            :style="{ '--tw-ring-color': 'var(--primary-color)' }"
                            @input="hasInitialized = true" />
                        <button
                            v-if="searchQuery"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)] focus:outline-none"
                            aria-label="Clear search"
                            @click="searchQuery = ''">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Filter row -->
                <div class="flex flex-wrap items-end gap-2">
                    <SlidersHorizontal class="mb-2 w-4 h-4 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />

                    <!-- Module -->
                    <Select v-model="moduleModel">
                        <SelectTrigger class="h-8 w-48 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    All modules
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.modules"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Event -->
                    <Select v-model="eventModel">
                        <SelectTrigger class="h-8 w-36 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    All events
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.events"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- Person -->
                    <Select v-model="causerModel">
                        <SelectTrigger class="h-8 w-48 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem :value="FILTER_ALL" class="text-xs text-[var(--color-text-muted)]">
                                    Anyone
                                </SelectItem>
                                <SelectItem
                                    v-for="opt in filterOptions.causers"
                                    :key="String(opt.value)"
                                    :value="String(opt.value)"
                                    class="text-xs">
                                    {{ opt.name }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>

                    <!-- When -->
                    <DateRangePicker
                        id="activity-range"
                        class="w-72"
                        v-model:from="filters.date_from"
                        v-model:to="filters.date_to" />

                    <Button
                        v-if="filtersActive"
                        variant="ghost"
                        size="sm"
                        class="mb-0.5 h-8 px-2 text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
                        @click="clearFilters">
                        <X class="w-3 h-3 mr-1" />
                        Clear
                    </Button>
                </div>
            </div>

            <Datatable
                :data="logs.data"
                :columns="columns"
                :pagination="pagination"
                :enable-search="false"
                :enable-row-click="true"
                :row-click="openEntry"
                empty-message="No activity found"
                empty-description="Changes to billing invoices, concerns and remittance advices will appear here."
                export-file-name="activity_logs"
                @update:pagination="(newPagination: typeof pagination) => { hasInitialized = true; pagination = newPagination }" />
        </div>

        <RightPane
            :open="rightPaneVisible"
            :title="rightPaneTitle"
            :loading="rightPaneLoading"
            :error="rightPaneError"
            :content-component="rightPaneContentComponent"
            :component-props="rightPaneComponentProps"
            @update:open="(v) => { if (!v && !rightPaneLoading) closePane('right') }" />
    </AppLayout>
</template>
