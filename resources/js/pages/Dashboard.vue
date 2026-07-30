<script setup lang="ts">
import {
    BarChart,
    ChartCard,
    ChartLegend,
    DonutChart,
    LineChart,
    StatTile,
    compactCurrency,
    formatNumber,
    toneVar,
    type ChartDatum,
    type ChartTableRow,
} from '@/components/charts';
import DashboardEmptyNotice from '@/components/dashboard/DashboardEmptyNotice.vue';
import DashboardFilters from '@/components/dashboard/DashboardFilters.vue';
import UserReportTable from '@/components/dashboard/UserReportTable.vue';
import { DEFAULT_PRESET, useDashboardFilters } from '@/composables/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type {
    BillingTrend,
    BreadcrumbItem,
    DashboardDataWindow,
    DashboardFilterOptions,
    DashboardFilters as DashboardFilterState,
    DashboardSummary,
    MetricBucket,
    TopAccount,
    UserReportRow,
} from '@/types';
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Analytics dashboard.
 *
 * The page is composition only: it maps the server payload onto the neutral chart shapes
 * and lets the primitives in `@/components/charts` do the drawing. Each widget states the
 * job its form was chosen for — magnitude (bars), part-to-whole (donut), change over time
 * (line), one number (stat tile) — and ships a table twin so no value is locked behind a
 * hover or a color.
 */
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const filters = computed(
    () => (page.props as any).filters as DashboardFilterState,
);
const filterOptions = computed(
    () =>
        ((page.props as any).filter_options ?? {
            presets: [],
            users: [],
        }) as DashboardFilterOptions,
);
const summary = computed(() => (page.props as any).summary as DashboardSummary);
const dataWindow = computed(
    () =>
        ((page.props as any).data_window ?? null) as DashboardDataWindow | null,
);
const agingBuckets = computed(
    () => ((page.props as any).aging_buckets ?? []) as MetricBucket[],
);
const statusBuckets = computed(
    () => ((page.props as any).status_buckets ?? []) as MetricBucket[],
);
const billingTrend = computed(
    () => (page.props as any).billing_trend as BillingTrend,
);
const topAccounts = computed(
    () => ((page.props as any).top_accounts ?? []) as TopAccount[],
);
const userReports = computed(
    () => ((page.props as any).user_reports ?? null) as UserReportRow[] | null,
);
const canViewUserReports = computed(() =>
    Boolean((page.props as any).can_view_user_reports),
);

const { processing, isCustomRange, isFiltered, apply, reset } =
    useDashboardFilters(() => filters.value);

/* ── Aging: magnitude across ordered buckets ─────────────────────────────────
   One accent hue for the buckets that are already overdue and the de-emphasis gray for
   the ones that are not — emphasis rather than a seven-step ramp, which no single hue can
   provide with visibly distinct steps. Every bar is directly labelled with its count. */
const agingItems = computed<ChartDatum[]>(() =>
    agingBuckets.value.map((bucket) => ({
        key: bucket.key,
        label: bucket.label,
        value: bucket.count,
        valueLabel: formatNumber(bucket.count),
        secondaryLabel: bucket.amount_formatted,
        emphasis: bucket.emphasis,
        href: bucket.href,
    })),
);

const agingLegend = [
    {
        key: 'past-due',
        label: 'Past due',
        tone: 'series-1' as const,
        shape: 'rect' as const,
    },
    {
        key: 'current',
        label: 'Not yet past due',
        tone: 'muted' as const,
        shape: 'rect' as const,
    },
];

const agingTotal = computed(() =>
    agingBuckets.value.reduce((total, bucket) => total + bucket.count, 0),
);

const agingTableRows = computed<ChartTableRow[]>(() =>
    agingBuckets.value.map((bucket) => ({
        key: bucket.key,
        cells: {
            label: bucket.label,
            count: formatNumber(bucket.count),
            amount: bucket.amount_formatted,
        },
    })),
);

/* ── Status: part-to-whole — tones from SoaStatus::tone(), colors from --viz-status-* ─ */
const statusItems = computed<ChartDatum[]>(() =>
    statusBuckets.value.map((bucket) => ({
        key: bucket.key,
        label: bucket.label,
        value: bucket.count,
        valueLabel: formatNumber(bucket.count),
        tone: bucket.tone ?? 'status-unpaid',
        href: bucket.href,
    })),
);

const statusTotal = computed(() =>
    statusBuckets.value.reduce((total, bucket) => total + bucket.count, 0),
);

const statusTableRows = computed<ChartTableRow[]>(() =>
    statusBuckets.value.map((bucket) => ({
        key: bucket.key,
        cells: {
            label: bucket.label,
            count: formatNumber(bucket.count),
            amount: bucket.amount_formatted,
        },
    })),
);

/* ── Trend: two money series, therefore one shared axis ─────────────────────── */
const trendPoints = computed(() => billingTrend.value?.points ?? []);

const trendIsEmpty = computed(() =>
    trendPoints.value.every(
        (point) => point.billed === 0 && point.collected === 0,
    ),
);

const trendLegend = computed(() =>
    (billingTrend.value?.series ?? []).map((series) => ({
        key: series.key,
        label: series.label,
        tone: series.token,
        shape: 'line' as const,
    })),
);

const trendTableRows = computed<ChartTableRow[]>(() =>
    trendPoints.value.map((point) => ({
        key: point.key,
        cells: {
            period: point.label,
            billed: point.billed_formatted,
            collected: point.collected_formatted,
            count: formatNumber(point.count),
        },
    })),
);

/* ── Accounts: ranking by outstanding balance ───────────────────────────────── */
const accountItems = computed<ChartDatum[]>(() =>
    topAccounts.value.map((account) => ({
        key: account.key,
        label: account.label,
        value: account.outstanding_amount,
        valueLabel: account.outstanding_formatted,
        secondaryLabel: `${formatNumber(account.count)} invoice(s) · ${account.billed_formatted} billed`,
        href: account.href,
    })),
);

const accountTableRows = computed<ChartTableRow[]>(() =>
    topAccounts.value.map((account) => ({
        key: account.key,
        cells: {
            label: account.label,
            count: formatNumber(account.count),
            billed: account.billed_formatted,
            outstanding: account.outstanding_formatted,
        },
    })),
);

/* ── Users (staff only): the leading uploaders as bars, everyone in the table ── */
const TOP_USER_BARS = 8;

const userItems = computed<ChartDatum[]>(() =>
    (userReports.value ?? [])
        .filter((row) => row.invoice_count > 0)
        .slice(0, TOP_USER_BARS)
        .map((row) => ({
            key: row.key,
            label: row.name,
            value: row.invoice_count,
            valueLabel: formatNumber(row.invoice_count),
            secondaryLabel: `${row.billed_formatted} billed · ${row.scope_label}`,
            emphasis:
                filters.value.user_id === null ||
                filters.value.user_id === row.user_id,
        })),
);

const userTableRows = computed<ChartTableRow[]>(() =>
    (userReports.value ?? []).map((row) => ({
        key: row.key,
        cells: {
            label: row.name,
            count: formatNumber(row.invoice_count),
            billed: row.billed_formatted,
            outstanding: row.outstanding_formatted,
        },
    })),
);

const activeUserName = computed(() => {
    const id = filters.value.user_id;
    if (id === null) {
        return null;
    }

    return (
        (userReports.value ?? []).find((row) => row.user_id === id)?.name ??
        filterOptions.value.users.find((option) => option.value === id)?.name ??
        `User #${id}`
    );
});

/** Collection-rate meter: the track is a lighter step of the fill's own hue. */
const collectionMeter = computed(() => {
    const rate = Math.min(
        Math.max(summary.value?.collection_rate.value ?? 0, 0),
        100,
    );
    const tone = toneVar(summary.value?.collection_rate.tone);

    return {
        width: `${rate}%`,
        fill: tone,
        track: `color-mix(in srgb, ${tone} 22%, transparent)`,
    };
});

const openList = (item: ChartDatum) => {
    if (item.href) {
        router.get(item.href);
    }
};

const scopeToUser = (userId: number | null) => apply({ user_id: userId });

/**
 * Nothing matched the current filter. Distinguished from "the page is broken" by the
 * notice above the widgets, which names the window that does hold data.
 */
const isEmptyResult = computed(
    () => (summary.value?.invoices.value ?? 0) === 0,
);

const showAllTime = () =>
    apply({ preset: DEFAULT_PRESET, date_from: null, date_to: null });
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
            <DashboardFilters
                :filters="filters"
                :options="filterOptions"
                :can-select-user="canViewUserReports"
                :is-custom-range="isCustomRange"
                :is-filtered="isFiltered"
                :processing="processing"
                @apply="apply"
                @reset="reset"
            />

            <p v-if="activeUserName" class="text-sm text-muted-foreground">
                <Users class="mr-1 inline size-4" aria-hidden="true" />
                Reporting on
                <span class="font-medium text-foreground">{{
                    activeUserName
                }}</span
                >'s records only.
            </p>

            <DashboardEmptyNotice
                v-if="isEmptyResult"
                :filters="filters"
                :data-window="dataWindow"
                :user-name="activeUserName"
                @show-all-time="showAllTime"
                @clear-user="scopeToUser(null)"
            />

            <!-- KPI row: the numbers that need no chart. -->
            <div
                v-if="summary"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
                :class="
                    processing
                        ? 'opacity-60 transition-opacity'
                        : 'transition-opacity'
                "
            >
                <StatTile
                    :stat="summary.outstanding"
                    hero
                    class="sm:col-span-2"
                />
                <StatTile :stat="summary.billed" />
                <StatTile :stat="summary.collected" />
                <StatTile :stat="summary.collection_rate">
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full"
                        :style="{ backgroundColor: collectionMeter.track }"
                    >
                        <div
                            class="h-full rounded-full transition-[width] duration-300"
                            :style="{
                                width: collectionMeter.width,
                                backgroundColor: collectionMeter.fill,
                            }"
                        />
                    </div>
                </StatTile>
                <StatTile :stat="summary.past_due" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <ChartCard
                    title="Invoice aging"
                    :description="`${formatNumber(summary?.invoices.value ?? 0)} invoice(s) · click a bar to open the filtered list`"
                    :loading="processing"
                    :empty="agingTotal === 0"
                    empty-text="No billing invoices in the selected period."
                    :table-columns="[
                        { key: 'label', label: 'Aging bucket' },
                        { key: 'count', label: 'Invoices', align: 'right' },
                        { key: 'amount', label: 'Amount', align: 'right' },
                    ]"
                    :table-rows="agingTableRows"
                >
                    <BarChart
                        :items="agingItems"
                        clickable
                        @select="openList"
                    />

                    <template #legend>
                        <ChartLegend :items="agingLegend" />
                    </template>
                </ChartCard>

                <ChartCard
                    title="Invoice status"
                    description="Share of invoices by settlement state"
                    :loading="processing"
                    :empty="statusTotal === 0"
                    empty-text="No billing invoices in the selected period."
                    :table-columns="[
                        { key: 'label', label: 'Status' },
                        { key: 'count', label: 'Invoices', align: 'right' },
                        { key: 'amount', label: 'Amount', align: 'right' },
                    ]"
                    :table-rows="statusTableRows"
                >
                    <DonutChart
                        :items="statusItems"
                        center-label="Invoices"
                        clickable
                        @select="openList"
                    />
                </ChartCard>
            </div>

            <ChartCard
                title="Billed vs collected"
                :description="`${billingTrend?.granularity_label ?? 'Monthly'} totals across the selected period`"
                :loading="processing"
                :empty="trendPoints.length === 0 || trendIsEmpty"
                empty-text="No billing activity to plot for the selected period."
                :table-columns="[
                    { key: 'period', label: 'Period' },
                    { key: 'billed', label: 'Billed', align: 'right' },
                    { key: 'collected', label: 'Collected', align: 'right' },
                    { key: 'count', label: 'Invoices', align: 'right' },
                ]"
                :table-rows="trendTableRows"
            >
                <LineChart
                    :points="trendPoints"
                    :series="billingTrend?.series ?? []"
                    :height="280"
                    :format-value="(value: number) => compactCurrency(value)"
                    aria-label="Billed and collected amounts over time"
                />

                <template #legend>
                    <ChartLegend :items="trendLegend" />
                </template>
            </ChartCard>

            <div
                class="grid gap-4"
                :class="canViewUserReports ? 'lg:grid-cols-2' : ''"
            >
                <ChartCard
                    title="Accounts with the largest balance"
                    description="Outstanding value per account · click to open the filtered list"
                    :loading="processing"
                    :empty="accountItems.length === 0"
                    empty-text="No outstanding balance in the selected period."
                    :table-columns="[
                        { key: 'label', label: 'Account' },
                        { key: 'count', label: 'Invoices', align: 'right' },
                        { key: 'billed', label: 'Billed', align: 'right' },
                        {
                            key: 'outstanding',
                            label: 'Outstanding',
                            align: 'right',
                        },
                    ]"
                    :table-rows="accountTableRows"
                >
                    <BarChart
                        :items="accountItems"
                        clickable
                        @select="openList"
                    />
                </ChartCard>

                <!-- Deferred server-side: the metric widgets paint first, this arrives
                     immediately after and holds a placeholder in the meantime rather than
                     claiming "no data". -->
                <Deferred v-if="canViewUserReports" data="user_reports">
                    <template #fallback>
                        <ChartCard
                            title="Invoices per user"
                            description="Loading user activity…"
                            loading
                            empty
                            empty-text="Loading…"
                        />
                    </template>

                    <ChartCard
                        title="Invoices per user"
                        description="Uploaded, or billed to the accounts they are assigned to · click to report on that user"
                        :loading="processing"
                        :empty="userItems.length === 0"
                        empty-text="No invoices are attributed to any user in the selected period."
                        :table-columns="[
                            { key: 'label', label: 'User' },
                            { key: 'count', label: 'Invoices', align: 'right' },
                            { key: 'billed', label: 'Billed', align: 'right' },
                            {
                                key: 'outstanding',
                                label: 'Outstanding',
                                align: 'right',
                            },
                        ]"
                        :table-rows="userTableRows"
                    >
                        <BarChart
                            :items="userItems"
                            clickable
                            @select="
                                (item) =>
                                    scopeToUser(
                                        Number(item.key.replace('user-', '')) ||
                                            null,
                                    )
                            "
                        />
                    </ChartCard>
                </Deferred>
            </div>

            <Deferred v-if="canViewUserReports" data="user_reports">
                <template #fallback>
                    <ChartCard
                        title="User activity report"
                        description="Loading user activity…"
                        loading
                        empty
                        empty-text="Loading…"
                    />
                </template>

                <ChartCard
                    title="User activity report"
                    description="Invoices attributed to each user (uploads, or their assigned accounts), plus the concerns and payments they recorded"
                    content-class="px-4"
                >
                    <UserReportTable
                        :rows="userReports ?? []"
                        :active-user-id="filters.user_id"
                        :processing="processing"
                        @select="scopeToUser"
                    />
                </ChartCard>
            </Deferred>
        </div>
    </AppLayout>
</template>
