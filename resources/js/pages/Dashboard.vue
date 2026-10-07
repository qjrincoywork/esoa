<script setup lang="ts">
import {
    BarChart,
    ChartCard,
    ChartLegend,
    DonutChart,
    LineChart,
    compactCurrency,
    formatNumber,
    type ChartDatum,
    type ChartLegendItem,
    type ChartTableColumn,
    type ChartTableRow,
} from '@/components/charts';
import DashboardEmptyNotice from '@/components/dashboard/DashboardEmptyNotice.vue';
import DashboardFilters from '@/components/dashboard/DashboardFilters.vue';
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import DashboardKpis from '@/components/dashboard/DashboardKpis.vue';
import DashboardSection from '@/components/dashboard/DashboardSection.vue';
import UserReportTable from '@/components/dashboard/UserReportTable.vue';
import { DEFAULT_PRESET, useDashboardFilters } from '@/composables/dashboard';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type {
    AppPageProps,
    BreadcrumbItem,
    DashboardFilterOptions,
    DashboardPageProps,
    MetricBucket,
} from '@/types';
import { Deferred, Head, router, usePage } from '@inertiajs/vue3';
import { Building2, Hourglass, TrendingUp, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * The administrator's dashboard.
 *
 * Laid out in the order the questions are asked: what the figures cover (the header and
 * its filters), how much is owed (the headline figures), how old it is and how it is
 * being settled, how billing is moving over time, and who holds it — the accounts and,
 * for staff, the users behind it. The page is composition only: it maps the server
 * payload onto the neutral chart shapes and the primitives in `@/components/charts` do
 * the drawing, each with a table twin so no value is locked behind a hover or a color.
 */
const page = usePage<AppPageProps<DashboardPageProps>>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const EMPTY_OPTIONS: DashboardFilterOptions = { presets: [], users: [] };

const filters = computed(() => page.props.filters);
const filterOptions = computed(() => page.props.filter_options ?? EMPTY_OPTIONS);
const summary = computed(() => page.props.summary ?? null);
const dataWindow = computed(() => page.props.data_window ?? null);
const agingBuckets = computed(() => page.props.aging_buckets ?? []);
const statusBuckets = computed(() => page.props.status_buckets ?? []);
const billingTrend = computed(() => page.props.billing_trend ?? null);
const topAccounts = computed(() => page.props.top_accounts ?? []);
const userReports = computed(() => page.props.user_reports ?? []);
const canViewUserReports = computed(() => Boolean(page.props.can_view_user_reports));

const { processing, isCustomRange, isFiltered, apply, reset } =
    useDashboardFilters(() => filters.value);

/* ── Table twins ──────────────────────────────────────────────────────────── */
const amountColumns = (first: string): ChartTableColumn[] => [
    { key: 'label', label: first },
    { key: 'count', label: 'Invoices', align: 'right' },
    { key: 'amount', label: 'Amount', align: 'right' },
];

const AGING_COLUMNS = amountColumns('Aging bucket');
const STATUS_COLUMNS = amountColumns('Status');

const TREND_COLUMNS: ChartTableColumn[] = [
    { key: 'period', label: 'Period' },
    { key: 'billed', label: 'Billed', align: 'right' },
    { key: 'collected', label: 'Collected', align: 'right' },
    { key: 'count', label: 'Invoices', align: 'right' },
];

const balanceColumns = (first: string): ChartTableColumn[] => [
    { key: 'label', label: first },
    { key: 'count', label: 'Invoices', align: 'right' },
    { key: 'billed', label: 'Billed', align: 'right' },
    { key: 'outstanding', label: 'Outstanding', align: 'right' },
];

const ACCOUNT_COLUMNS = balanceColumns('Account');
const USER_COLUMNS = balanceColumns('User');

/* ── Buckets: aging and status share one shape, so one mapping serves both ── */
const bucketTotal = (buckets: MetricBucket[]): number =>
    buckets.reduce((total, bucket) => total + bucket.count, 0);

const bucketRows = (buckets: MetricBucket[]): ChartTableRow[] =>
    buckets.map((bucket) => ({
        key: bucket.key,
        cells: {
            label: bucket.label,
            count: formatNumber(bucket.count),
            amount: bucket.amount_formatted,
        },
    }));

const bucketItem = (bucket: MetricBucket): ChartDatum => ({
    key: bucket.key,
    label: bucket.label,
    value: bucket.count,
    valueLabel: formatNumber(bucket.count),
    secondaryLabel: bucket.amount_formatted,
    href: bucket.href,
});

/* Aging: one accent for buckets already past due, the de-emphasis gray for the rest —
   emphasis rather than a seven-step ramp no single hue can keep distinct. */
const agingItems = computed<ChartDatum[]>(() =>
    agingBuckets.value.map((bucket) => ({ ...bucketItem(bucket), emphasis: bucket.emphasis })),
);
const agingTotal = computed(() => bucketTotal(agingBuckets.value));
const agingRows = computed(() => bucketRows(agingBuckets.value));

const AGING_LEGEND: ChartLegendItem[] = [
    { key: 'past-due', label: 'Past due', tone: 'series-1', shape: 'rect' },
    { key: 'current', label: 'Not yet past due', tone: 'muted', shape: 'rect' },
];

/* Status: part-to-whole; tones from SoaStatus::tone(), colors from --viz-status-*. */
const statusItems = computed<ChartDatum[]>(() =>
    statusBuckets.value.map((bucket) => ({ ...bucketItem(bucket), tone: bucket.tone ?? 'status-unpaid' })),
);
const statusTotal = computed(() => bucketTotal(statusBuckets.value));
const statusRows = computed(() => bucketRows(statusBuckets.value));

/* ── Trend: two money series, therefore one shared axis ─────────────────────── */
const trendPoints = computed(() => billingTrend.value?.points ?? []);

const trendIsEmpty = computed(() =>
    trendPoints.value.every((point) => point.billed === 0 && point.collected === 0),
);

const trendLegend = computed<ChartLegendItem[]>(() =>
    (billingTrend.value?.series ?? []).map((series) => ({
        key: series.key,
        label: series.label,
        tone: series.token,
        shape: 'line',
    })),
);

const trendRows = computed<ChartTableRow[]>(() =>
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

const accountRows = computed<ChartTableRow[]>(() =>
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

/* ── Users (staff only): the leading users as bars, everyone in the report ──── */
const TOP_USER_BARS = 8;

const userItems = computed<ChartDatum[]>(() =>
    userReports.value
        .filter((row) => row.invoice_count > 0)
        .slice(0, TOP_USER_BARS)
        .map((row) => ({
            key: row.key,
            label: row.name,
            value: row.invoice_count,
            valueLabel: formatNumber(row.invoice_count),
            secondaryLabel: `${row.billed_formatted} billed · ${row.scope_label}`,
            emphasis: filters.value.user_id === null || filters.value.user_id === row.user_id,
        })),
);

const userRows = computed<ChartTableRow[]>(() =>
    userReports.value.map((row) => ({
        key: row.key,
        cells: {
            label: row.name,
            count: formatNumber(row.invoice_count),
            billed: row.billed_formatted,
            outstanding: row.outstanding_formatted,
        },
    })),
);

/** Bars carry the report row's key; the user it stands for is looked up, never parsed out of it. */
const userIdByKey = computed(
    () => new Map(userReports.value.map((row) => [row.key, row.user_id])),
);

const activeUserName = computed(() => {
    const id = filters.value.user_id;
    if (id === null) return null;

    return (
        userReports.value.find((row) => row.user_id === id)?.name ??
        filterOptions.value.users.find((option) => option.value === id)?.name ??
        `User #${id}`
    );
});

/* ── Actions ───────────────────────────────────────────────────────────────── */
const openList = (item: ChartDatum) => {
    if (item.href) router.get(item.href);
};

const scopeToUser = (userId: number | null) => apply({ user_id: userId });

const selectUserBar = (item: ChartDatum) => scopeToUser(userIdByKey.value.get(item.key) ?? null);

const showAllTime = () =>
    apply({ preset: DEFAULT_PRESET, date_from: null, date_to: null });

/**
 * Nothing matched the current filter. Distinguished from "the page is broken" by the
 * notice above the widgets, which names the window that does hold data.
 */
const isEmptyResult = computed(() => (summary.value?.invoices.value ?? 0) === 0);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-6 overflow-x-auto p-4">
            <DashboardHeader
                :filters="filters"
                :summary="summary"
                :data-window="dataWindow"
                :user-name="activeUserName"
                :processing="processing"
                @clear-user="scopeToUser(null)"
                @clear-account="apply({ account_code: null })"
            >
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
            </DashboardHeader>

            <DashboardEmptyNotice
                v-if="isEmptyResult"
                :filters="filters"
                :data-window="dataWindow"
                :user-name="activeUserName"
                @show-all-time="showAllTime"
                @clear-user="scopeToUser(null)"
            />

            <!-- The figures that need no chart -->
            <DashboardKpis
                v-if="summary"
                :summary="summary"
                :status-buckets="statusBuckets"
                :processing="processing"
            />

            <DashboardSection
                title="Receivables"
                description="How long open invoices have been waiting, and how every invoice has been settled."
                :icon="Hourglass"
            >
                <div class="grid gap-4 lg:grid-cols-2">
                    <ChartCard
                        title="Invoice aging"
                        :description="`${formatNumber(summary?.invoices.value ?? 0)} invoice(s) by age · select a bar to open its list`"
                        :loading="processing"
                        :empty="agingTotal === 0"
                        empty-text="No billing invoices in the selected period."
                        :table-columns="AGING_COLUMNS"
                        :table-rows="agingRows"
                    >
                        <BarChart :items="agingItems" clickable @select="openList" />

                        <template #legend>
                            <ChartLegend :items="AGING_LEGEND" />
                        </template>
                    </ChartCard>

                    <ChartCard
                        title="Invoice status"
                        description="Share of invoices by settlement state · select one to open its list"
                        :loading="processing"
                        :empty="statusTotal === 0"
                        empty-text="No billing invoices in the selected period."
                        :table-columns="STATUS_COLUMNS"
                        :table-rows="statusRows"
                    >
                        <DonutChart
                            :items="statusItems"
                            center-label="Invoices"
                            clickable
                            @select="openList"
                        />
                    </ChartCard>
                </div>
            </DashboardSection>

            <DashboardSection
                title="Billing trend"
                description="What was billed against what was collected, period by period."
                :icon="TrendingUp"
            >
                <ChartCard
                    title="Billed vs collected"
                    :description="`${billingTrend?.granularity_label ?? 'Monthly'} totals across the selected period`"
                    :loading="processing"
                    :empty="trendPoints.length === 0 || trendIsEmpty"
                    empty-text="No billing activity to plot for the selected period."
                    :table-columns="TREND_COLUMNS"
                    :table-rows="trendRows"
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
            </DashboardSection>

            <DashboardSection
                :title="canViewUserReports ? 'Accounts & users' : 'Accounts'"
                :description="
                    canViewUserReports
                        ? 'Where the open balance sits, and who the invoices are attributed to.'
                        : 'Where the open balance sits.'
                "
                :icon="Building2"
            >
                <div class="grid gap-4" :class="canViewUserReports && 'lg:grid-cols-2'">
                    <ChartCard
                        title="Accounts with the largest balance"
                        description="Outstanding value per account · select one to open its invoices"
                        :loading="processing"
                        :empty="accountItems.length === 0"
                        empty-text="No outstanding balance in the selected period."
                        :table-columns="ACCOUNT_COLUMNS"
                        :table-rows="accountRows"
                    >
                        <BarChart :items="accountItems" clickable @select="openList" />
                    </ChartCard>

                    <!-- Deferred server-side: the metric widgets paint first and this holds a
                         placeholder meanwhile, rather than claiming "no data". -->
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
                            description="Uploaded, or billed to their assigned accounts · select one to report on that user"
                            :loading="processing"
                            :empty="userItems.length === 0"
                            empty-text="No invoices are attributed to any user in the selected period."
                            :table-columns="USER_COLUMNS"
                            :table-rows="userRows"
                        >
                            <BarChart :items="userItems" clickable @select="selectUserBar" />
                        </ChartCard>
                    </Deferred>
                </div>
            </DashboardSection>

            <DashboardSection
                v-if="canViewUserReports"
                title="Team activity"
                description="Invoices attributed to each user, plus the concerns and payments they recorded · select a name to report on that user."
                :icon="Users"
            >
                <Deferred data="user_reports">
                    <template #fallback>
                        <ChartCard
                            title="User activity report"
                            description="Loading user activity…"
                            loading
                            empty
                            empty-text="Loading…"
                        />
                    </template>

                    <ChartCard title="User activity report" content-class="px-4">
                        <UserReportTable
                            :rows="userReports"
                            :active-user-id="filters.user_id"
                            :processing="processing"
                            @select="scopeToUser"
                        />
                    </ChartCard>
                </Deferred>
            </DashboardSection>
        </div>
    </AppLayout>
</template>
