<script setup lang="ts">
import {
    StackedBar,
    StatTile,
    formatNumber,
    toneVar,
    type ChartDatum,
} from '@/components/charts';
import { cn } from '@/lib/utils';
import type { DashboardSummary, MetricBucket } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The headline figures, read before any chart.
 *
 * One hero — the outstanding balance, the number an administrator opens this page for —
 * with what it is made of underneath, so "how much is owed" and "in what state" are
 * answered in the same place. The four supporting figures sit beside it in a 2×2 block:
 * what was billed, what came back, the rate between the two, and how many invoices are
 * already late. The invoice count itself is context rather than a target, so it lives in
 * the page header, not in a tile.
 */
const props = withDefaults(
    defineProps<{
        summary: DashboardSummary;
        statusBuckets?: MetricBucket[];
        processing?: boolean;
    }>(),
    { statusBuckets: () => [], processing: false },
);

/**
 * The balance split by status. Which statuses count as outstanding is the server's call
 * (`MetricBucketResource`), the same rule the outstanding total is summed with, so the
 * parts always add up to the hero figure above them.
 */
const outstandingParts = computed<ChartDatum[]>(() =>
    props.statusBuckets
        .filter((bucket) => bucket.outstanding)
        .map((bucket) => ({
            key: bucket.key,
            label: bucket.label,
            value: bucket.amount,
            valueLabel: bucket.amount_formatted,
            secondaryLabel: `${formatNumber(bucket.count)} invoice${bucket.count === 1 ? '' : 's'}`,
            tone: bucket.tone ?? 'status-unpaid',
            href: bucket.href,
        })),
);

const hasOutstanding = computed(() => props.summary.outstanding.value > 0);

/** Collection-rate meter: the track is a lighter step of the fill's own hue. */
const collectionMeter = computed(() => {
    const rate = Math.min(Math.max(props.summary.collection_rate.value, 0), 100);
    const tone = toneVar(props.summary.collection_rate.tone);

    return {
        width: `${rate}%`,
        fill: tone,
        track: `color-mix(in srgb, ${tone} 22%, transparent)`,
    };
});

/** The figures beside the hero, in reading order: in, back, the rate, and what is late. */
const supporting = computed(() => [
    props.summary.billed,
    props.summary.collected,
    props.summary.collection_rate,
    props.summary.past_due,
]);

const openList = (item: ChartDatum) => {
    if (item.href) router.get(item.href);
};
</script>

<template>
    <div
        :class="
            cn(
                'grid gap-4 transition-opacity duration-200 sm:grid-cols-2 lg:grid-cols-4',
                processing && 'opacity-60',
            )
        "
        :aria-busy="processing"
    >
        <StatTile
            :stat="summary.outstanding"
            hero
            class="sm:col-span-2 lg:row-span-2"
        >
            <div v-if="hasOutstanding && outstandingParts.length" class="mt-auto pt-5">
                <p class="mb-2 text-xs font-medium text-muted-foreground">
                    By status · select one to open its invoices
                </p>
                <StackedBar
                    :items="outstandingParts"
                    label="Outstanding balance by invoice status"
                    clickable
                    @select="openList"
                />
            </div>
        </StatTile>

        <StatTile v-for="stat in supporting" :key="stat.key" :stat="stat">
            <div
                v-if="stat.key === 'collection_rate'"
                class="mt-auto pt-3"
            >
                <div
                    class="h-1.5 w-full overflow-hidden rounded-full"
                    :style="{ backgroundColor: collectionMeter.track }"
                    role="meter"
                    :aria-valuenow="stat.value"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="stat.label"
                >
                    <div
                        class="h-full rounded-full transition-[width] duration-300"
                        :style="{
                            width: collectionMeter.width,
                            backgroundColor: collectionMeter.fill,
                        }"
                    />
                </div>
            </div>
        </StatTile>
    </div>
</template>
