<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import type {
    DashboardDataWindow,
    DashboardFilters,
    DashboardSummary,
} from '@/types';
import {
    Building2,
    CalendarRange,
    FileText,
    Loader2,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

/**
 * Title, scope and filters of the dashboard, in one frame above everything they govern.
 *
 * It answers "what am I looking at" before any figure does: the period and breakdown in
 * the title row, how much data that covers beside it, and — as removable chips — any
 * narrowing to one user or one account, since a dashboard quietly scoped to one person
 * is the easiest one to misread. The filter controls themselves are slotted in, so this
 * owns the framing and the page owns the filter state.
 */
const props = withDefaults(
    defineProps<{
        filters: DashboardFilters;
        summary?: DashboardSummary | null;
        dataWindow?: DashboardDataWindow | null;
        /** Name of the user the dashboard is reporting on, when narrowed to one. */
        userName?: string | null;
        processing?: boolean;
    }>(),
    { summary: null, dataWindow: null, userName: null, processing: false },
);

const emit = defineEmits<{
    clearUser: [];
    clearAccount: [];
}>();

const breakdown = computed(() =>
    props.filters.granularity === 'day' ? 'Daily breakdown' : 'Monthly breakdown',
);

/** "1,284 invoices · 312 account(s) billed" — how much data the figures below stand on. */
const invoiceCoverage = computed(() => {
    const invoices = props.summary?.invoices;
    if (!invoices) return null;

    const count = `${invoices.formatted} ${invoices.value === 1 ? 'invoice' : 'invoices'}`;

    return [count, invoices.hint].filter(Boolean).join(' · ');
});

/** Facts about the slice on screen, as data so a new one is an entry, not more markup. */
const meta = computed(() =>
    [
        { key: 'invoices', icon: FileText, text: invoiceCoverage.value },
        {
            key: 'window',
            icon: CalendarRange,
            text: props.dataWindow ? `Data from ${props.dataWindow.label}` : null,
        },
    ].filter((item) => item.text),
);

interface ScopeChip {
    key: string;
    icon: Component;
    label: string;
    value: string;
    clear: () => void;
}

/** Narrowing beyond the period, each removable on its own. */
const chips = computed<ScopeChip[]>(() => {
    const list: ScopeChip[] = [];

    if (props.filters.user_id !== null) {
        list.push({
            key: 'user',
            icon: UserRound,
            label: 'User',
            value: props.userName ?? `User #${props.filters.user_id}`,
            clear: () => emit('clearUser'),
        });
    }

    if (props.filters.account_code) {
        list.push({
            key: 'account',
            icon: Building2,
            label: 'Account',
            value: props.filters.account_code,
            clear: () => emit('clearAccount'),
        });
    }

    return list;
});
</script>

<template>
    <Card class="gap-0 py-0">
        <CardHeader
            class="flex flex-col gap-3 border-b px-4 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 [.border-b]:pb-5"
        >
            <div class="min-w-0 space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg leading-none font-semibold tracking-tight">
                        Admin Dashboard
                    </h1>
                    <span
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ filters.label }} · {{ breakdown }}
                    </span>
                </div>
                <CardDescription>
                    Billing invoices, collections and team activity across the
                    accounts in your scope.
                </CardDescription>
            </div>

            <div
                class="flex flex-col gap-1 text-xs text-muted-foreground sm:items-end"
                aria-live="polite"
            >
                <span
                    v-for="item in meta"
                    :key="item.key"
                    class="inline-flex items-center gap-1.5"
                >
                    <component :is="item.icon" class="size-3.5" aria-hidden="true" />
                    {{ item.text }}
                </span>
                <span v-if="processing" class="inline-flex items-center gap-1.5">
                    <Loader2 class="size-3.5 animate-spin" aria-hidden="true" />
                    Updating…
                </span>
            </div>
        </CardHeader>

        <CardContent class="flex flex-col gap-3 px-4 py-4 sm:px-6">
            <slot />

            <div
                v-if="chips.length"
                class="flex flex-wrap items-center gap-2"
                aria-label="Active filters"
            >
                <span class="text-xs text-muted-foreground">Narrowed to</span>
                <span
                    v-for="chip in chips"
                    :key="chip.key"
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 py-0.5 pr-1 pl-2.5 text-xs dark:bg-primary/10"
                >
                    <component :is="chip.icon" class="size-3.5 text-muted-foreground" aria-hidden="true" />
                    <span class="text-muted-foreground">{{ chip.label }}:</span>
                    <span class="max-w-48 truncate font-medium text-foreground" :title="chip.value">
                        {{ chip.value }}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-5 cursor-pointer rounded-full"
                        :disabled="processing"
                        :aria-label="`Remove ${chip.label.toLowerCase()} filter`"
                        @click="chip.clear"
                    >
                        <X class="size-3" aria-hidden="true" />
                    </Button>
                </span>
            </div>
        </CardContent>
    </Card>
</template>
