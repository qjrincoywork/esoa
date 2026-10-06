<script setup lang="ts">
/**
 * The audit trail: who changed what, in which module, and when.
 *
 * Read-only by design — there is no create, edit or delete here, because the value of
 * a trail is that it cannot be edited. Navigation is all filtering and reading: narrow
 * by module, event, person or date, then open a row to see the field-by-field changes.
 */
import { computed, h, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { createColumnHelper, type ColumnDef } from '@tanstack/vue-table';
import { type BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';
import Datatable from '@/components/Datatable.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import ListSearch from '@/components/ListSearch.vue';
import RightPane from '@/components/RightPane.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { useActivityLogs, type ActivityLogRow } from '@/composables/activityLogs';
import { useServerListing, type ListingPage } from '@/composables/datatable/useServerListing';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { avatar } from '@/lib/avatar';
import { badge } from '@/lib/directoryBadges';
import { timeAgo } from '@/lib/relativeTime';
import { Bot, ChevronRight, SlidersHorizontal, X } from 'lucide-vue-next';

type LogsPagination = ListingPage & { data: ActivityLogRow[] }

type Option = { value: string | number; name: string }

/** What `ActivityLogController::index` sends. */
interface ActivityLogsPageProps {
    activity_logs?: LogsPagination
    filter_options?: { modules?: Option[]; events?: Option[]; causers?: Option[] }
}

const page = usePage();
const pageProps = computed(() => page.props as unknown as ActivityLogsPageProps);
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

const EMPTY_PAGE: LogsPagination = { current_page: 1, per_page: 10, total: 0, data: [] };

const logs = computed(() => pageProps.value.activity_logs ?? EMPTY_PAGE);

const searchQuery = ref('');

// --- Filters ---
type FilterKey = 'log_name' | 'event' | 'causer_id'

const emptyFilters = () => ({ log_name: '', event: '', causer_id: '', date_from: '', date_to: '' });
const filters = ref(emptyFilters());

const activeFilterCount = computed(() => {
    const { date_from, date_to, ...rest } = filters.value;

    // A date range is one filter, however many of its ends are set.
    return Object.values(rest).filter((value) => value !== '').length + (date_from || date_to ? 1 : 0);
});

const toSelectOptions = (options: Option[] = []) => options.map(({ value, name }) => ({ value: String(value), label: name }));

/** One dropdown of the filter bar: what it narrows, how "no filter" reads, and its choices. */
interface FilterDef {
    key: FilterKey
    label: string
    allLabel: string
    options: { value: string; label: string }[]
    width: string
}

/** The dropdowns of the filter bar, in order. */
const filterDefs = computed<FilterDef[]>(() => {
    const options = pageProps.value.filter_options;

    return [
        { key: 'log_name', label: 'Module', allLabel: 'All modules', options: toSelectOptions(options?.modules), width: 'w-44' },
        { key: 'event', label: 'Event', allLabel: 'All events', options: toSelectOptions(options?.events), width: 'w-40' },
        { key: 'causer_id', label: 'Changed by', allLabel: 'Anyone', options: toSelectOptions(options?.causers), width: 'w-48' },
    ];
});

/** A day as the date inputs hold it (YYYY-MM-DD), in local time — not UTC, which can be a day off. */
const toDateValue = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/** Ranges a trail is usually read over, ending today; `days` counts back before it. */
const DATE_PRESETS = [
    { key: 'today', label: 'Today', days: 0 },
    { key: '7d', label: '7 days', days: 6 },
    { key: '30d', label: '30 days', days: 29 },
];

const presetRange = (days: number) => {
    const from = new Date();
    from.setDate(from.getDate() - days);

    return { from: toDateValue(from), to: toDateValue(new Date()) };
};

/** The preset the chosen range matches, if any — so picking the same dates by hand lights it too. */
const activePreset = computed(() => DATE_PRESETS.find(({ days }) => {
    const { from, to } = presetRange(days);

    return filters.value.date_from === from && filters.value.date_to === to;
})?.key);

/** Apply a preset, or lift it when it is already the range shown. */
const togglePreset = (preset: (typeof DATE_PRESETS)[number]) => {
    const { from, to } = activePreset.value === preset.key ? { from: '', to: '' } : presetRange(preset.days);

    filters.value.date_from = from;
    filters.value.date_to = to;
};

const clearFilters = () => {
    filters.value = emptyFilters();
};

// --- Summary & empty state ---
const isNarrowed = computed(() => searchQuery.value.trim() !== '' || activeFilterCount.value > 0);

const resultSummary = computed(() => {
    const total = logs.value.total;

    return `${total.toLocaleString()} ${isNarrowed.value ? 'matching ' : ''}${total === 1 ? 'entry' : 'entries'}`;
});

const emptyState = computed(() => isNarrowed.value
    ? { message: 'No activity matches your search', description: 'Try a different search term, widen the dates, or clear the filters.' }
    : { message: 'No activity yet', description: 'Changes to billing invoices, concerns and remittance advices will appear here.' });

// --- Columns ---
const DASH = '—';

/**
 * Event pills by outcome: green adds, blue edits, red removes, amber brings back or half
 * succeeds. The batch events come from the batch upload (`AuditEvent`); anything else is grey.
 */
const EVENT_CLASSES: Record<string, string> = {
    created: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    updated: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    deleted: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    restored: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    batch_uploaded: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
    batch_partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    batch_rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    batch_failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};
const NEUTRAL_EVENT = 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';

/** The server's label, tidied: an event it has no label for arrives as its raw name ("Batch_uploaded"). */
const eventLabel = (row: ActivityLogRow) => (row.event_label ?? row.event ?? DASH).replace(/_/g, ' ');

/** The exact time to read, and beneath it how long ago — for scanning recency down the column. */
const whenCell = (row: ActivityLogRow) => h('div', { class: 'whitespace-nowrap' }, [
    h('div', null, row.logged_at ?? DASH),
    row.logged_at_value ? h('div', { class: 'text-muted-foreground' }, timeAgo(row.logged_at_value)) : null,
]);

/** Who made the change; an entry without a person was written by the system (a job or import). */
const causerCell = (row: ActivityLogRow) => h('div', { class: 'flex items-center gap-2' }, [
    row.causer ? avatar(row.causer) : avatar(null, Bot),
    h('span', { class: ['truncate', !row.causer && 'text-muted-foreground'] }, row.causer ?? 'System'),
]);

const columnHelper = createColumnHelper<ActivityLogRow>();

/**
 * No module column: every description already opens with the module ("Billing invoice
 * BI-1 was updated"), and the module filter narrows by it.
 */
const columns: ColumnDef<ActivityLogRow, any>[] = [
    columnHelper.accessor('logged_at', {
        header: 'When',
        // The label reads well but sorts alphabetically; sort on the timestamp instead.
        sortingFn: (a, b) =>
            String(a.original.logged_at_value ?? '').localeCompare(String(b.original.logged_at_value ?? '')),
        cell: ({ row }) => whenCell(row.original),
    }),
    columnHelper.accessor('event_label', {
        header: 'Event',
        cell: ({ row }) => badge(eventLabel(row.original), EVENT_CLASSES[row.original.event ?? ''] ?? NEUTRAL_EVENT),
    }),
    columnHelper.accessor('description', {
        header: 'Activity',
        cell: (info) => h('span', { class: 'font-medium' }, info.getValue() ?? DASH),
    }),
    columnHelper.accessor('causer', {
        header: 'Changed by',
        cell: ({ row }) => causerCell(row.original),
    }),
    columnHelper.accessor('change_count', {
        header: 'Changes',
        // How much changed, so a one-field edit reads apart from a rewrite without opening it.
        cell: (info) => {
            const count = Number(info.getValue()) || 0;

            return count
                ? h('span', { class: 'whitespace-nowrap tabular-nums' }, `${count} ${count === 1 ? 'field' : 'fields'}`)
                : h('span', { class: 'text-muted-foreground' }, DASH);
        },
    }),
    // Says the row opens; the whole row is the target.
    columnHelper.display({
        id: 'open',
        header: '',
        cell: () => h(ChevronRight, { class: 'size-4 text-muted-foreground', 'aria-hidden': 'true' }),
    }),
];

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Activity Logs',
        href: slug.value,
    },
];

// --- Fetching ---
const filterParams = (): Record<string, string> => {
    const params: Record<string, string> = {};

    if (searchQuery.value.trim()) params.search_string = searchQuery.value.trim();
    for (const [key, value] of Object.entries(filters.value)) {
        if (value !== '') params[key] = value;
    }

    return params;
};

const { pagination, isFetching, queueFetch, onPaginationChange } = useServerListing({
    listing: () => logs.value,
    url: () => `/${slug.value}`,
    params: filterParams,
    visit: () => ({ only: ['activity_logs'] }),
});

// Typing reloads once it pauses; spacing alone changes nothing the server would see.
watch(() => searchQuery.value.trim(), () => queueFetch(500));
watch(filters, () => queueFetch(300), { deep: true });

const openEntry = (row: ActivityLogRow) => openActivityLog(row);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Activity Logs" />

        <div class="flex flex-1 flex-col gap-4 p-4">
            <Card class="gap-0 py-0">
                <!-- Title and live result count -->
                <CardHeader class="flex flex-col gap-1.5 border-b px-4 py-5 sm:px-6 [.border-b]:pb-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg leading-none font-semibold tracking-tight">Activity Logs</h1>
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium tabular-nums text-muted-foreground"
                            aria-live="polite">
                            {{ resultSummary }}
                        </span>
                    </div>
                    <CardDescription>
                        A read-only record of changes to billing invoices, concerns and remittance advices.
                        Open an entry to see exactly what changed.
                    </CardDescription>
                </CardHeader>

                <!-- Search and filters -->
                <CardContent class="flex flex-col gap-3 border-b px-4 py-4 sm:px-6">
                    <ListSearch
                        id="activity-search"
                        v-model="searchQuery"
                        label="Search activity"
                        placeholder="Search record or person..." />

                    <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filters">
                        <span class="flex items-center gap-1.5 pr-1 text-xs font-medium text-muted-foreground">
                            <SlidersHorizontal class="size-3.5" aria-hidden="true" /> Filters
                        </span>

                        <FilterSelect
                            v-for="def in filterDefs"
                            :key="def.key"
                            v-model="filters[def.key]"
                            :label="def.label"
                            :all-label="def.allLabel"
                            :options="def.options"
                            :class="def.width" />

                        <DateRangePicker
                            id="activity-range"
                            class="w-72"
                            v-model:from="filters.date_from"
                            v-model:to="filters.date_to" />

                        <!-- Common ranges in one click; the active one is pressed -->
                        <div class="flex items-center gap-1" role="group" aria-label="Date presets">
                            <Button
                                v-for="preset in DATE_PRESETS"
                                :key="preset.key"
                                type="button"
                                size="sm"
                                :variant="activePreset === preset.key ? 'secondary' : 'ghost'"
                                class="h-9 cursor-pointer px-2.5"
                                :class="activePreset !== preset.key && 'text-muted-foreground'"
                                :aria-pressed="activePreset === preset.key"
                                @click="togglePreset(preset)">
                                {{ preset.label }}
                            </Button>
                        </div>

                        <Button
                            v-if="activeFilterCount"
                            variant="ghost"
                            size="sm"
                            class="h-9 cursor-pointer px-2 text-muted-foreground hover:text-foreground"
                            @click="clearFilters">
                            <X class="size-3.5" /> Clear filters ({{ activeFilterCount }})
                        </Button>
                    </div>
                </CardContent>

                <!-- The trail; dimmed while a reload is in flight -->
                <CardContent
                    class="px-4 py-4 transition-opacity duration-200 sm:px-6"
                    :class="{ 'opacity-60': isFetching }"
                    :aria-busy="isFetching">
                    <Datatable
                        :data="logs.data"
                        :columns="columns"
                        :pagination="pagination"
                        :enable-search="false"
                        :enable-row-click="true"
                        :row-click="openEntry"
                        :empty-message="emptyState.message"
                        :empty-description="emptyState.description"
                        export-file-name="activity_logs"
                        @update:pagination="onPaginationChange" />
                </CardContent>
            </Card>
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
