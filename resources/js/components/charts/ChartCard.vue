<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { ChartColumn, Table as TableIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { ChartTableColumn, ChartTableRow } from './types';

/**
 * Frame shared by every chart on the dashboard.
 *
 * It owns the three things a chart must never be shipped without:
 *  - the table twin, so no value is reachable only by hovering a colored mark;
 *  - the empty state, so a filter that returns nothing says so instead of drawing
 *    an axis around a void;
 *  - the refetch behavior — the previous render is held at reduced opacity rather
 *    than replaced by a skeleton, so filtering never makes the page jump.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        /** Dim (but keep) the current render while new data is on the way. */
        loading?: boolean;
        /** Render the empty state instead of the chart. */
        empty?: boolean;
        emptyText?: string;
        /** Supplying both enables the chart/table toggle. */
        tableColumns?: ChartTableColumn[];
        tableRows?: ChartTableRow[];
        class?: string;
        contentClass?: string;
    }>(),
    {
        loading: false,
        empty: false,
        emptyText: 'No data for the selected period.',
    },
);

const view = ref<'chart' | 'table'>('chart');

/**
 * The toggle appears only when there is something to switch to: an empty card would
 * otherwise offer a "table" view of the same "no data" sentence.
 */
const hasTable = computed(
    () =>
        !props.empty &&
        (props.tableColumns?.length ?? 0) > 0 &&
        (props.tableRows?.length ?? 0) > 0,
);

const activeView = computed(() => (hasTable.value ? view.value : 'chart'));

const toggleClass = (target: 'chart' | 'table') =>
    cn(
        'inline-flex h-7 w-7 items-center justify-center rounded-md transition-colors',
        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        activeView.value === target
            ? 'bg-background text-foreground shadow-xs'
            : 'text-muted-foreground hover:text-foreground',
    );
</script>

<template>
    <Card :class="cn('gap-4 py-4', props.class)">
        <CardHeader class="gap-1 px-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <CardTitle class="text-sm font-semibold">{{
                        title
                    }}</CardTitle>
                    <CardDescription v-if="description" class="mt-1 text-xs">
                        {{ description }}
                    </CardDescription>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <slot name="actions" />

                    <div
                        v-if="hasTable"
                        class="flex items-center gap-0.5 rounded-lg bg-muted p-0.5"
                    >
                        <button
                            type="button"
                            :class="toggleClass('chart')"
                            :aria-pressed="activeView === 'chart'"
                            aria-label="Show chart"
                            @click="view = 'chart'"
                        >
                            <ChartColumn class="size-4" />
                        </button>
                        <button
                            type="button"
                            :class="toggleClass('table')"
                            :aria-pressed="activeView === 'table'"
                            aria-label="Show table of values"
                            @click="view = 'table'"
                        >
                            <TableIcon class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </CardHeader>

        <CardContent
            :class="
                cn(
                    'px-4 transition-opacity duration-200',
                    loading ? 'pointer-events-none opacity-50' : 'opacity-100',
                    props.contentClass,
                )
            "
        >
            <p
                v-if="empty"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                {{ emptyText }}
            </p>

            <template v-else-if="activeView === 'chart'">
                <slot />
                <div v-if="$slots.legend" class="mt-4">
                    <slot name="legend" />
                </div>
            </template>

            <div v-else class="max-h-80 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-card">
                        <tr class="border-b text-xs text-muted-foreground">
                            <th
                                v-for="column in tableColumns"
                                :key="column.key"
                                scope="col"
                                :class="[
                                    'py-2 font-medium',
                                    column.align === 'right'
                                        ? 'text-right'
                                        : 'text-left',
                                ]"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in tableRows"
                            :key="row.key"
                            class="border-b last:border-0"
                        >
                            <td
                                v-for="column in tableColumns"
                                :key="column.key"
                                :class="[
                                    'py-2',
                                    column.align === 'right'
                                        ? 'text-right tabular-nums'
                                        : 'text-left text-muted-foreground',
                                ]"
                            >
                                {{ row.cells[column.key] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>

        <slot name="footer" />
    </Card>
</template>
