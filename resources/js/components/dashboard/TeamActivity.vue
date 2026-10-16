<script setup lang="ts">
import { CURRENCY_SIGN, compactCurrency, formatNumber, toneVar } from '@/components/charts';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import type { UserReportRow } from '@/types';
import {
    Briefcase,
    Building2,
    ChevronDown,
    FileCheck2,
    Handshake,
    MessageSquareWarning,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch, type Component } from 'vue';

/**
 * Per-user activity (staff roles only), split by who the user is to an invoice.
 *
 * The people in this report stand on opposite sides of the same invoice, so one list
 * with one set of labels misreads all of them:
 *  - client HR POCs (group account admins and account/branch admins) endorse and pay
 *    the invoices billed to their accounts — their balance is what they still owe;
 *  - VC employees bill the invoices and collect against them — their balance is what is
 *    still to be collected;
 *  - brokers follow the invoices of the accounts under their agent code.
 * Each group gets its own view, figures and wording. The grouping is the server's
 * attribution scope ({@see App\Enums\DataScope}), the same rule the figures are summed by.
 *
 * Users whose attributed invoices are identical (every broker on one agent code, POCs on
 * the same accounts) are gathered into one shared card that states the figures once.
 * Selecting a user narrows the whole dashboard to them.
 */
const props = withDefaults(
    defineProps<{
        rows: UserReportRow[];
        activeUserId?: number | null;
        processing?: boolean;
    }>(),
    { activeUserId: null, processing: false },
);

const emit = defineEmits<{ select: [userId: number | null] }>();

/* ── Audiences ─────────────────────────────────────────────────────────────── */
/** Mirrors App\Enums\DataScope. */
const SCOPE = { OWNED_RECORDS: 1, ASSIGNED_ACCOUNTS: 2, AGENT_ACCOUNTS: 3 } as const;

type AudienceKey = 'client' | 'vc' | 'broker';

interface Audience {
    key: AudienceKey;
    scope: number;
    label: string;
    /** Plural noun for counts, e.g. "3 HR POCs". */
    noun: string;
    nounSingular: string;
    icon: Component;
    /** What these users do with an invoice, in one sentence. */
    role: string;
    figures: { invoices: string; billed: string; outstanding: string; settled: string };
    concerns: string;
    payments: string;
    /**
     * Whether identical figures mean a shared portfolio. Never for uploads: every invoice
     * has one uploader, so two VC employees can only match by coincidence.
     */
    shares: boolean;
    sharedHeading: (count: number) => string;
}

const AUDIENCES: Audience[] = [
    {
        key: 'client',
        scope: SCOPE.ASSIGNED_ACCOUNTS,
        label: 'Client HR POCs',
        noun: 'HR POCs',
        nounSingular: 'HR POC',
        icon: Building2,
        role: 'Group account admins and account/branch admins on the client side. They endorse and pay the invoices billed to their accounts.',
        figures: { invoices: 'Invoices', billed: 'Billed to them', outstanding: 'Unpaid', settled: 'Paid' },
        concerns: 'Concerns raised',
        payments: 'Remittances sent',
        shares: true,
        sharedHeading: (count) => `${count} HR POCs handle the same accounts`,
    },
    {
        key: 'vc',
        scope: SCOPE.OWNED_RECORDS,
        label: 'VC employees',
        noun: 'VC employees',
        nounSingular: 'VC employee',
        icon: Briefcase,
        role: 'Our staff. They bill every invoice and collect the payment against it.',
        figures: { invoices: 'Invoices billed', billed: 'Amount billed', outstanding: 'To collect', settled: 'Collected' },
        concerns: 'Concerns logged',
        payments: 'Remittances recorded',
        shares: false,
        sharedHeading: (count) => `${count} VC employees`,
    },
    {
        key: 'broker',
        scope: SCOPE.AGENT_ACCOUNTS,
        label: 'Brokers',
        noun: 'brokers',
        nounSingular: 'broker',
        icon: Handshake,
        role: 'Agents who follow up the invoices of the accounts under their agent code.',
        figures: { invoices: 'Invoices', billed: 'Billed', outstanding: 'Unpaid', settled: 'Paid' },
        concerns: 'Concerns raised',
        payments: 'Remittances sent',
        shares: true,
        sharedHeading: (count) => `${count} brokers share one agent portfolio`,
    },
];

const audienceOf = (row: UserReportRow): Audience =>
    AUDIENCES.find((audience) => audience.scope === row.scope) ?? AUDIENCES[1];

const rowsByAudience = computed(() => {
    const groups = new Map<AudienceKey, UserReportRow[]>(AUDIENCES.map((a) => [a.key, []]));
    props.rows.forEach((row) => groups.get(audienceOf(row).key)!.push(row));

    return groups;
});

const audienceOptions = computed(() =>
    AUDIENCES.map((audience) => ({
        audience,
        count: rowsByAudience.value.get(audience.key)?.length ?? 0,
    })).filter((option) => option.count > 0),
);

const activeKey = ref<AudienceKey>('client');

/* Land on a group that has people in it, and stay valid when a filter empties one. */
watch(
    audienceOptions,
    (options) => {
        if (options.length && !options.some((option) => option.audience.key === activeKey.value)) {
            activeKey.value = options[0].audience.key;
        }
    },
    { immediate: true },
);

const audience = computed(() => AUDIENCES.find((a) => a.key === activeKey.value) ?? AUDIENCES[0]);
const audienceRows = computed(() => rowsByAudience.value.get(activeKey.value) ?? []);

/* ── Toolbar state ─────────────────────────────────────────────────────────── */
type SortKey = 'outstanding' | 'invoices' | 'concerns';

const sorts = computed<Array<{ value: SortKey; label: string }>>(() => [
    { value: 'outstanding', label: audience.value.figures.outstanding },
    { value: 'invoices', label: 'Invoices' },
    { value: 'concerns', label: 'Concerns' },
]);

/** Individual cards shown before "Show all"; shared portfolios are few and always shown. */
const SOLO_PAGE = 9;
/** Avatars drawn in a shared portfolio's stack before folding into "+N". */
const STACK_SIZE = 5;

const query = ref('');
const roleFilter = ref<string | null>(null);
const sort = ref<SortKey>('outstanding');
const showAllSolo = ref(false);
const showIdle = ref(false);

watch(activeKey, () => {
    roleFilter.value = null;
    showIdle.value = false;
});

watch([query, roleFilter, sort, activeKey], () => {
    showAllSolo.value = false;
});

/* ── Classification ────────────────────────────────────────────────────────── */
const humanize = (role: string): string => {
    const words = role.replace(/[_-]+/g, ' ').trim();

    return words.charAt(0).toUpperCase() + words.slice(1);
};

/** The user's own title: their user type, else their first role (e.g. "Billing admin"). */
const roleLabel = (row: UserReportRow): string => {
    if (row.type_label && row.type_label !== '—' && row.type_label !== 'Unknown') return row.type_label;

    return row.roles.length ? humanize(row.roles[0]) : 'Staff';
};

const hasActivity = (row: UserReportRow): boolean =>
    row.invoice_count > 0 || row.concern_count > 0 || row.payment_count > 0;

/**
 * Users share a portfolio when the same scope yields the same invoices. Users with no
 * attributed invoices never share: their zero figures say nothing about one another.
 */
const portfolioKey = (row: UserReportRow): string =>
    audienceOf(row).shares && row.invoice_count > 0
        ? `${row.scope}|${row.invoice_count}|${row.billed_amount}|${row.outstanding_amount}`
        : row.key;

const roleOptions = computed(() => {
    const counts = new Map<string, number>();
    audienceRows.value.forEach((row) => counts.set(roleLabel(row), (counts.get(roleLabel(row)) ?? 0) + 1));

    return [...counts.entries()]
        .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
        .map(([label, count]) => ({ label, count }));
});

/* ── Group figures ─────────────────────────────────────────────────────────── */
const sumOf = (rows: UserReportRow[], pick: (row: UserReportRow) => number): number =>
    rows.reduce((total, row) => total + pick(row), 0);

const exactMoney = (value: number): string =>
    CURRENCY_SIGN + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * The strip above the cards. Amounts are only summed for VC employees: each invoice has
 * exactly one uploader, whereas POCs and brokers overlap on the same accounts, so adding
 * their balances would count one invoice several times.
 */
const stats = computed(() => {
    const rows = audienceRows.value;
    const a = audience.value;
    const people = { key: 'people', label: a.label, value: formatNumber(rows.length), exact: null as string | null };
    const concerns = { key: 'concerns', label: a.concerns, value: formatNumber(sumOf(rows, (r) => r.concern_count)), exact: null };
    const payments = { key: 'payments', label: a.payments, value: formatNumber(sumOf(rows, (r) => r.payment_count)), exact: null };

    if (a.key === 'vc') {
        const toCollect = sumOf(rows, (r) => r.outstanding_amount);

        return [
            people,
            { key: 'invoices', label: a.figures.invoices, value: formatNumber(sumOf(rows, (r) => r.invoice_count)), exact: null },
            { key: 'outstanding', label: 'Still to collect', value: compactCurrency(toCollect), exact: exactMoney(toCollect) },
            payments,
        ];
    }

    return [
        people,
        {
            key: 'unpaid',
            label: `With unpaid invoices`,
            value: formatNumber(rows.filter((r) => r.outstanding_amount > 0).length),
            exact: null,
        },
        concerns,
        payments,
    ];
});

/* ── Filtering and grouping ────────────────────────────────────────────────── */
interface Portfolio {
    key: string;
    scopeLabel: string;
    scopeDescription: string;
    invoiceCount: number;
    billedAmount: number;
    billedFormatted: string;
    outstandingAmount: number;
    outstandingFormatted: string;
    concernCount: number;
    paymentCount: number;
    members: UserReportRow[];
}

const visibleRows = computed(() => {
    const needle = query.value.trim().toLowerCase();

    return audienceRows.value.filter((row) => {
        if (roleFilter.value !== null && roleLabel(row) !== roleFilter.value) return false;
        if (needle === '') return true;

        return `${row.name} ${row.email ?? ''} ${row.username}`.toLowerCase().includes(needle);
    });
});

const memberWeight = (row: UserReportRow): number => row.concern_count + row.payment_count;

const metric = (portfolio: Portfolio): number => {
    switch (sort.value) {
        case 'invoices':
            return portfolio.invoiceCount;
        case 'concerns':
            return portfolio.concernCount;
        default:
            return portfolio.outstandingAmount;
    }
};

const portfolios = computed<Portfolio[]>(() => {
    const groups = new Map<string, Portfolio>();

    visibleRows.value.filter(hasActivity).forEach((row) => {
        const key = portfolioKey(row);
        let group = groups.get(key);

        if (!group) {
            group = {
                key,
                scopeLabel: row.scope_label,
                scopeDescription: row.scope_description,
                invoiceCount: row.invoice_count,
                billedAmount: row.billed_amount,
                billedFormatted: row.billed_formatted,
                outstandingAmount: row.outstanding_amount,
                outstandingFormatted: row.outstanding_formatted,
                concernCount: 0,
                paymentCount: 0,
                members: [],
            };
            groups.set(key, group);
        }

        group.concernCount += row.concern_count;
        group.paymentCount += row.payment_count;
        group.members.push(row);
    });

    return [...groups.values()]
        .map((group) => ({
            ...group,
            members: [...group.members].sort(
                (a, b) => memberWeight(b) - memberWeight(a) || a.name.localeCompare(b.name),
            ),
        }))
        .sort((a, b) => metric(b) - metric(a) || b.invoiceCount - a.invoiceCount);
});

const shared = computed(() => portfolios.value.filter((p) => p.members.length > 1));
const solo = computed(() => portfolios.value.filter((p) => p.members.length === 1));
const soloShown = computed(() =>
    showAllSolo.value ? solo.value : solo.value.slice(0, SOLO_PAGE),
);
const idle = computed(() => visibleRows.value.filter((row) => !hasActivity(row)));

const nothingMatches = computed(() => audienceRows.value.length > 0 && visibleRows.value.length === 0);

/* ── Presentation helpers ──────────────────────────────────────────────────── */
const initials = (row: UserReportRow): string => getInitials(row.name) || '?';

const plural = (count: number, singular: string, pluralWord = `${singular}s`): string =>
    `${formatNumber(count)} ${count === 1 ? singular : pluralWord}`;

const figures = (portfolio: Portfolio) => [
    { key: 'invoices', label: audience.value.figures.invoices, value: formatNumber(portfolio.invoiceCount), exact: null },
    { key: 'billed', label: audience.value.figures.billed, value: compactCurrency(portfolio.billedAmount), exact: portfolio.billedFormatted },
    { key: 'outstanding', label: audience.value.figures.outstanding, value: compactCurrency(portfolio.outstandingAmount), exact: portfolio.outstandingFormatted },
];

/** Share of the billed amount no longer outstanding; null when nothing was billed. */
const settledShare = (portfolio: Portfolio): number | null => {
    if (portfolio.billedAmount <= 0) return null;

    const share = ((portfolio.billedAmount - portfolio.outstandingAmount) / portfolio.billedAmount) * 100;

    return Math.min(Math.max(share, 0), 100);
};

const settledPercent = (portfolio: Portfolio): number =>
    Math.round(settledShare(portfolio) ?? 0);

const METER_FILL = toneVar('good');
const METER_TRACK = `color-mix(in srgb, ${METER_FILL} 20%, transparent)`;

const roleSummary = (portfolio: Portfolio): string =>
    [...new Set(portfolio.members.map(roleLabel))].join(', ');

const isActive = (row: UserReportRow): boolean => props.activeUserId === row.user_id;

const toggle = (row: UserReportRow) => {
    emit('select', isActive(row) ? null : row.user_id);
};

const clearFilters = () => {
    query.value = '';
    roleFilter.value = null;
};

const chipClass = (active: boolean) =>
    cn(
        'inline-flex h-7 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        active
            ? 'border-foreground bg-foreground text-background'
            : 'border-border text-muted-foreground hover:border-foreground/40 hover:text-foreground',
    );

const sortClass = (active: boolean) =>
    cn(
        'inline-flex h-7 items-center rounded-md px-2.5 text-xs font-medium transition-colors',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        active
            ? 'bg-background text-foreground shadow-xs'
            : 'text-muted-foreground hover:text-foreground',
    );

const audienceClass = (active: boolean) =>
    cn(
        'flex flex-col gap-1 rounded-xl border p-3 text-left transition-colors',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        active
            ? 'border-primary bg-primary/5 ring-1 ring-primary dark:bg-primary/10'
            : 'bg-card hover:border-foreground/30',
    );

/** The whole card is the target; the name's button is stretched over it. */
const STRETCHED_BUTTON =
    'text-left after:absolute after:inset-0 after:rounded-[inherit] focus-visible:outline-none';
</script>

<template>
    <div
        :class="cn('flex flex-col gap-4 transition-opacity duration-200', processing && 'pointer-events-none opacity-50')"
        :aria-busy="processing"
    >
        <p
            v-if="rows.length === 0"
            class="rounded-xl border border-dashed py-10 text-center text-sm text-muted-foreground"
        >
            No user activity recorded for the selected period.
        </p>

        <template v-else>
            <!-- Who is who: each group, what it does with an invoice, and how many there are -->
            <div
                class="grid gap-3"
                :class="audienceOptions.length === 3 ? 'md:grid-cols-3' : audienceOptions.length === 2 ? 'md:grid-cols-2' : ''"
                role="group"
                aria-label="User groups"
            >
                <button
                    v-for="option in audienceOptions"
                    :key="option.audience.key"
                    type="button"
                    :class="audienceClass(activeKey === option.audience.key)"
                    :aria-pressed="activeKey === option.audience.key"
                    @click="activeKey = option.audience.key"
                >
                    <span class="flex items-center gap-2">
                        <component :is="option.audience.icon" class="size-4 text-muted-foreground" aria-hidden="true" />
                        <span class="text-sm font-semibold text-foreground">{{ option.audience.label }}</span>
                        <span class="ml-auto rounded-full bg-muted px-2 py-px text-xs font-semibold text-muted-foreground tabular-nums">
                            {{ formatNumber(option.count) }}
                        </span>
                    </span>
                    <span class="text-xs leading-snug text-muted-foreground">{{ option.audience.role }}</span>
                </button>
            </div>

            <!-- The active group at a glance -->
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border lg:grid-cols-4">
                <div v-for="stat in stats" :key="stat.key" class="bg-card px-4 py-3">
                    <dt class="text-xs text-muted-foreground">{{ stat.label }}</dt>
                    <dd
                        class="mt-1 text-xl font-semibold tracking-tight text-foreground tabular-nums"
                        :title="stat.exact ?? undefined"
                    >
                        {{ stat.value }}
                    </dd>
                </div>
            </dl>

            <!-- Toolbar -->
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative sm:w-64">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="query"
                            type="search"
                            :placeholder="`Search ${audience.noun} by name or email`"
                            :aria-label="`Search ${audience.noun}`"
                            class="h-8 pl-8"
                        />
                    </div>

                    <div
                        v-if="roleOptions.length > 1"
                        class="flex flex-wrap items-center gap-1.5"
                        role="group"
                        aria-label="Filter by role"
                    >
                        <button
                            type="button"
                            :class="chipClass(roleFilter === null)"
                            :aria-pressed="roleFilter === null"
                            @click="roleFilter = null"
                        >
                            All
                            <span class="tabular-nums opacity-70">{{ audienceRows.length }}</span>
                        </button>
                        <button
                            v-for="option in roleOptions"
                            :key="option.label"
                            type="button"
                            :class="chipClass(roleFilter === option.label)"
                            :aria-pressed="roleFilter === option.label"
                            @click="roleFilter = roleFilter === option.label ? null : option.label"
                        >
                            {{ option.label }}
                            <span class="tabular-nums opacity-70">{{ option.count }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start lg:self-auto">
                    <span class="text-xs text-muted-foreground">Sort by</span>
                    <div class="flex items-center gap-0.5 rounded-lg bg-muted p-0.5" role="group" aria-label="Sort by">
                        <button
                            v-for="option in sorts"
                            :key="option.value"
                            type="button"
                            :class="sortClass(sort === option.value)"
                            :aria-pressed="sort === option.value"
                            @click="sort = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="nothingMatches"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-sm text-muted-foreground"
            >
                No {{ audience.noun }} match these filters.
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-xs font-medium text-foreground underline-offset-2 hover:underline"
                    @click="clearFilters"
                >
                    <X class="size-3.5" aria-hidden="true" />
                    Clear filters
                </button>
            </div>

            <!-- Shared portfolios: figures stated once, members listed under them -->
            <article
                v-for="portfolio in shared"
                :key="portfolio.key"
                class="overflow-hidden rounded-xl border bg-card"
            >
                <header class="flex flex-col gap-4 p-4 xl:flex-row xl:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <div class="flex shrink-0 -space-x-2" aria-hidden="true">
                            <Avatar
                                v-for="member in portfolio.members.slice(0, STACK_SIZE)"
                                :key="member.key"
                                class="size-8 ring-2 ring-card"
                            >
                                <AvatarFallback class="text-[11px] font-semibold">
                                    {{ initials(member) }}
                                </AvatarFallback>
                            </Avatar>
                            <span
                                v-if="portfolio.members.length > STACK_SIZE"
                                class="relative flex size-8 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground ring-2 ring-card"
                            >
                                +{{ portfolio.members.length - STACK_SIZE }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-foreground">
                                {{ audience.sharedHeading(portfolio.members.length) }}
                            </h3>
                            <p class="text-xs text-muted-foreground" :title="portfolio.scopeDescription">
                                {{ roleSummary(portfolio) }} · figures counted once, not per person
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-end gap-x-8 gap-y-3">
                        <dl class="flex gap-6">
                            <div v-for="figure in figures(portfolio)" :key="figure.key">
                                <dt class="text-xs text-muted-foreground">{{ figure.label }}</dt>
                                <dd
                                    class="text-base font-semibold text-foreground tabular-nums"
                                    :title="figure.exact ?? undefined"
                                >
                                    {{ figure.value }}
                                </dd>
                            </div>
                        </dl>
                        <div v-if="settledShare(portfolio) !== null" class="w-36">
                            <div class="flex justify-between text-xs text-muted-foreground">
                                <span>{{ audience.figures.settled }}</span>
                                <span class="tabular-nums">{{ settledPercent(portfolio) }}%</span>
                            </div>
                            <div
                                class="mt-1.5 h-1.5 overflow-hidden rounded-full"
                                :style="{ backgroundColor: METER_TRACK }"
                                role="meter"
                                :aria-valuenow="settledPercent(portfolio)"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                :aria-label="`Share of billed amount ${audience.figures.settled.toLowerCase()}`"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{ width: `${settledShare(portfolio)}%`, backgroundColor: METER_FILL }"
                                />
                            </div>
                        </div>
                    </div>
                </header>

                <ul class="grid gap-2 border-t bg-muted/30 p-3 sm:grid-cols-2 xl:grid-cols-3">
                    <li
                        v-for="member in portfolio.members"
                        :key="member.key"
                        :class="
                            cn(
                                'relative flex items-center gap-3 rounded-lg border bg-card px-3 py-2 transition-colors',
                                'hover:border-foreground/30 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                isActive(member) && 'border-primary ring-1 ring-primary',
                            )
                        "
                    >
                        <Avatar class="size-7">
                            <AvatarFallback class="text-[10px] font-semibold">{{ initials(member) }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <button
                                type="button"
                                :class="cn(STRETCHED_BUTTON, 'block max-w-full truncate text-sm font-medium text-foreground')"
                                :title="`${member.name} (${member.username})`"
                                :aria-pressed="isActive(member)"
                                @click="toggle(member)"
                            >
                                {{ member.name }}
                            </button>
                            <p class="truncate text-xs text-muted-foreground">{{ member.email ?? member.username }}</p>
                        </div>
                        <Badge v-if="isActive(member)" class="shrink-0">Viewing</Badge>
                        <Badge v-else-if="!member.is_active" variant="outline" class="shrink-0">Inactive</Badge>
                        <div
                            v-else-if="memberWeight(member) > 0"
                            class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground tabular-nums"
                        >
                            <span v-if="member.concern_count" class="inline-flex items-center gap-1" :title="`${audience.concerns}: ${member.concern_count}`">
                                <MessageSquareWarning class="size-3.5" aria-hidden="true" />{{ member.concern_count }}
                            </span>
                            <span v-if="member.payment_count" class="inline-flex items-center gap-1" :title="`${audience.payments}: ${member.payment_count}`">
                                <FileCheck2 class="size-3.5" aria-hidden="true" />{{ member.payment_count }}
                            </span>
                        </div>
                    </li>
                </ul>
            </article>

            <!-- Users with figures of their own -->
            <ul v-if="solo.length" class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                <li
                    v-for="portfolio in soloShown"
                    :key="portfolio.key"
                    :class="
                        cn(
                            'relative flex flex-col gap-4 rounded-xl border bg-card p-4 transition-colors',
                            'hover:border-foreground/30 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                            isActive(portfolio.members[0]) && 'border-primary ring-1 ring-primary',
                        )
                    "
                >
                    <div class="flex items-start gap-3">
                        <Avatar class="size-9">
                            <AvatarFallback class="text-xs font-semibold">{{ initials(portfolio.members[0]) }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <button
                                type="button"
                                :class="cn(STRETCHED_BUTTON, 'block max-w-full truncate text-sm font-semibold text-foreground')"
                                :title="`${portfolio.members[0].name} (${portfolio.members[0].username})`"
                                :aria-pressed="isActive(portfolio.members[0])"
                                @click="toggle(portfolio.members[0])"
                            >
                                {{ portfolio.members[0].name }}
                            </button>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ portfolio.members[0].email ?? portfolio.members[0].username }}
                            </p>
                        </div>
                        <Badge v-if="isActive(portfolio.members[0])" class="shrink-0">Viewing</Badge>
                        <Badge v-else-if="!portfolio.members[0].is_active" variant="outline" class="shrink-0">Inactive</Badge>
                    </div>

                    <div class="flex flex-wrap gap-1.5 text-xs">
                        <span class="rounded-md bg-muted px-2 py-0.5 text-muted-foreground">
                            {{ roleLabel(portfolio.members[0]) }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-3 gap-3">
                        <div v-for="figure in figures(portfolio)" :key="figure.key" class="min-w-0">
                            <dt class="truncate text-xs text-muted-foreground" :title="figure.label">{{ figure.label }}</dt>
                            <dd
                                class="truncate text-base font-semibold text-foreground tabular-nums"
                                :title="figure.exact ?? undefined"
                            >
                                {{ figure.value }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="settledShare(portfolio) !== null">
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>{{ audience.figures.settled }}</span>
                            <span class="tabular-nums">{{ settledPercent(portfolio) }}%</span>
                        </div>
                        <div
                            class="mt-1.5 h-1.5 overflow-hidden rounded-full"
                            :style="{ backgroundColor: METER_TRACK }"
                            role="meter"
                            :aria-valuenow="settledPercent(portfolio)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`Share of billed amount ${audience.figures.settled.toLowerCase()}`"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{ width: `${settledShare(portfolio)}%`, backgroundColor: METER_FILL }"
                            />
                        </div>
                    </div>

                    <div class="mt-auto flex items-center justify-between gap-3 border-t pt-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-3 tabular-nums">
                            <span class="inline-flex items-center gap-1" :title="`${audience.concerns}: ${portfolio.concernCount}`">
                                <MessageSquareWarning class="size-3.5" aria-hidden="true" />
                                {{ portfolio.concernCount }}
                                <span class="sr-only">{{ audience.concerns }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1" :title="`${audience.payments}: ${portfolio.paymentCount}`">
                                <FileCheck2 class="size-3.5" aria-hidden="true" />
                                {{ portfolio.paymentCount }}
                                <span class="sr-only">{{ audience.payments }}</span>
                            </span>
                        </div>
                        <span class="truncate" :title="portfolio.members[0].last_activity_at ?? undefined">
                            {{ portfolio.members[0].last_activity_at ? `Last active ${portfolio.members[0].last_activity_at}` : 'No recent activity' }}
                        </span>
                    </div>
                </li>
            </ul>

            <button
                v-if="solo.length > SOLO_PAGE"
                type="button"
                class="self-center rounded-md px-3 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="showAllSolo = !showAllSolo"
            >
                {{ showAllSolo ? 'Show fewer' : `Show all ${solo.length} ${audience.noun}` }}
            </button>

            <!-- Users with nothing in the period, folded away -->
            <div v-if="idle.length" class="rounded-xl border border-dashed">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-expanded="showIdle"
                    @click="showIdle = !showIdle"
                >
                    <span>
                        {{ plural(idle.length, audience.nounSingular, audience.noun) }} with no invoices, concerns or remittances in this period
                    </span>
                    <ChevronDown
                        :class="cn('size-4 shrink-0 transition-transform', showIdle && 'rotate-180')"
                        aria-hidden="true"
                    />
                </button>
                <ul v-if="showIdle" class="flex flex-wrap gap-2 border-t border-dashed px-4 py-3">
                    <li
                        v-for="row in idle"
                        :key="row.key"
                        :class="
                            cn(
                                'relative flex items-center gap-2 rounded-full border bg-card py-1 pr-3 pl-1 text-xs transition-colors',
                                'hover:border-foreground/30 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                isActive(row) && 'border-primary ring-1 ring-primary',
                            )
                        "
                    >
                        <Avatar class="size-6">
                            <AvatarFallback class="text-[10px] font-semibold">{{ initials(row) }}</AvatarFallback>
                        </Avatar>
                        <button
                            type="button"
                            :class="cn(STRETCHED_BUTTON, 'font-medium text-foreground')"
                            :title="`${row.name} (${row.email ?? row.username})`"
                            :aria-pressed="isActive(row)"
                            @click="toggle(row)"
                        >
                            {{ row.name }}
                        </button>
                        <span class="text-muted-foreground">{{ roleLabel(row) }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
