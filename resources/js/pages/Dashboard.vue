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
import TeamActivity from '@/components/dashboard/TeamActivity.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
import { Building2, Hourglass, LayoutDashboard, TrendingUp, Users } from 'lucide-vue-next';
import { computed, onMounted, ref, watch, type Component } from 'vue';

/**
 * The administrator's dashboard.
 *
 * The header and its filters stay on top because they govern everything below; the
 * content is split into tabs so each question fits on one screen instead of a long
 * scroll: the overview (headline figures), the billing trend, receivables (how old
 * and how settled), the accounts holding the balance and, for staff, the users on both
 * sides of the invoices — client HR POCs who settle them, VC employees who bill and
 * collect them, and brokers. The page is composition only: it maps the server payload onto the neutral chart
 * shapes and the primitives in `@/components/charts` do the drawing, each with a table
 * twin so no value is locked behind a hover or a color.
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

/* ── Team (staff only) ─────────────────────────────────────────────────────── */
const activeUserName = computed(() => {
    const id = filters.value.user_id;
    if (id === null) return null;

    return (
        userReports.value.find((row) => row.user_id === id)?.name ??
        filterOptions.value.users.find((option) => option.value === id)?.name ??
        `User #${id}`
    );
});

/* ── Tabs ──────────────────────────────────────────────────────────────────── */
type TabKey = 'overview' | 'trend' | 'receivables' | 'accounts' | 'users';

interface DashboardTab {
    key: TabKey;
    label: string;
    icon: Component;
    /** Small count beside the label; null hides it. */
    count: string | null;
    countTitle?: string;
    /** Draw the count in the alert tone (e.g. invoices already past due). */
    alert?: boolean;
}

const tabs = computed<DashboardTab[]>(() => {
    const pastDue = summary.value?.past_due.value ?? 0;
    const list: DashboardTab[] = [
        { key: 'overview', label: 'Overview', icon: LayoutDashboard, count: null },
        { key: 'trend', label: 'Trend', icon: TrendingUp, count: null },
        {
            key: 'receivables',
            label: 'Receivables',
            icon: Hourglass,
            count: pastDue > 0 ? formatNumber(pastDue) : null,
            countTitle: 'Invoices past due',
            alert: true,
        },
        {
            key: 'accounts',
            label: 'Accounts',
            icon: Building2,
            count: topAccounts.value.length ? formatNumber(topAccounts.value.length) : null,
            countTitle: 'Accounts with an outstanding balance',
        },
    ];

    if (canViewUserReports.value) {
        // Deferred: undefined until it lands, so the count appears once it is known.
        const users = page.props.user_reports?.length;
        list.push({
            key: 'users',
            label: 'Users',
            icon: Users,
            count: users ? formatNumber(users) : null,
            countTitle: 'HR POCs, VC employees and brokers in the report',
        });
    }

    return list;
});

const activeTab = ref<TabKey>('overview');

/*
 * The open tab is mirrored in the URL hash so a refresh or a shared link reopens it.
 * Filter visits preserve component state, so the tab also survives every filter change.
 */
const isTabKey = (value: string): value is TabKey =>
    tabs.value.some((tab) => tab.key === value);

onMounted(() => {
    const fromHash = window.location.hash.slice(1);
    if (isTabKey(fromHash)) activeTab.value = fromHash;
});

watch(activeTab, (tab) => {
    const { pathname, search } = window.location;
    const hash = tab === 'overview' ? '' : `#${tab}`;
    window.history.replaceState(window.history.state, '', `${pathname}${search}${hash}`);
});

const tabTriggerClass = [
    'relative h-11 flex-none gap-2 rounded-none border-0 bg-transparent px-3 text-muted-foreground shadow-none',
    'hover:text-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none',
    // Inset ring and bottom-0 bar: the list scrolls sideways on phones, which clips anything outside it.
    'focus-visible:ring-inset focus-visible:ring-offset-0',
    'after:absolute after:inset-x-2 after:bottom-0 after:h-0.5 after:rounded-full after:bg-transparent data-[state=active]:after:bg-primary',
].join(' ');

/* ── Actions ───────────────────────────────────────────────────────────────── */
const openList = (item: ChartDatum) => {
    if (item.href) router.get(item.href);
};

const scopeToUser = (userId: number | null) => apply({ user_id: userId });

/** Picking a user means "report on them", so their figures are shown right away. */
const reportOnUser = (userId: number | null) => {
    scopeToUser(userId);
    if (userId !== null) activeTab.value = 'overview';
};

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

            <Tabs v-model="activeTab" class="flex flex-col gap-4">
                <div class="overflow-x-auto border-b">
                    <TabsList
                        class="h-auto justify-start gap-1 rounded-none bg-transparent p-0"
                        aria-label="Dashboard views"
                    >
                        <TabsTrigger
                            v-for="tab in tabs"
                            :key="tab.key"
                            :value="tab.key"
                            :class="tabTriggerClass"
                        >
                            <span class="inline-flex items-center gap-2">
                                <component :is="tab.icon" class="size-4" aria-hidden="true" />
                                {{ tab.label }}
                                <span
                                    v-if="tab.count"
                                    :class="[
                                        'rounded-full px-1.5 py-px text-[11px] leading-4 font-semibold tabular-nums',
                                        tab.alert
                                            ? 'bg-destructive/10 text-destructive dark:bg-destructive/20'
                                            : 'bg-muted text-muted-foreground',
                                    ]"
                                    :title="tab.countTitle"
                                >
                                    {{ tab.count }}
                                    <span class="sr-only">{{ tab.countTitle }}</span>
                                </span>
                            </span>
                        </TabsTrigger>
                    </TabsList>
                </div>

                <!-- Overview: how much is owed -->
                <TabsContent value="overview" class="mt-0">
                    <DashboardKpis
                        v-if="summary"
                        :summary="summary"
                        :status-buckets="statusBuckets"
                        :processing="processing"
                    />
                </TabsContent>

                <!-- Trend: how billing is moving -->
                <TabsContent value="trend" class="mt-0">
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
                            :height="320"
                            :format-value="(value: number) => compactCurrency(value)"
                            aria-label="Billed and collected amounts over time"
                        />

                        <template #legend>
                            <ChartLegend :items="trendLegend" />
                        </template>
                    </ChartCard>
                </TabsContent>

                <!-- Receivables: how old open invoices are, and how every invoice was settled -->
                <TabsContent value="receivables" class="mt-0">
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
                </TabsContent>

                <!-- Accounts: where the open balance sits -->
                <TabsContent value="accounts" class="mt-0">
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
                </TabsContent>

                <!-- Users (staff only): client HR POCs who settle the invoices, VC employees
                     who bill and collect them, and brokers -->
                <TabsContent v-if="canViewUserReports" value="users" class="mt-0 flex flex-col gap-4">
                    <p class="text-xs text-muted-foreground">
                        Everyone on both sides of an invoice, grouped by their role in it ·
                        select a person to narrow the dashboard to their invoices.
                    </p>

                    <!-- Deferred server-side: the metric widgets paint first and this holds a
                         placeholder meanwhile, rather than claiming "no data". -->
                    <Deferred data="user_reports">
                        <template #fallback>
                            <div class="flex flex-col gap-4" aria-busy="true">
                                <div class="grid gap-3 md:grid-cols-3">
                                    <Skeleton v-for="n in 3" :key="n" class="h-20 rounded-xl" />
                                </div>
                                <Skeleton class="h-[4.5rem] w-full rounded-xl" />
                                <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                                    <Skeleton v-for="n in 3" :key="n" class="h-52 rounded-xl" />
                                </div>
                                <span class="sr-only">Loading user activity…</span>
                            </div>
                        </template>

                        <TeamActivity
                            :rows="userReports"
                            :active-user-id="filters.user_id"
                            :processing="processing"
                            @select="reportOnUser"
                        />
                    </Deferred>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
