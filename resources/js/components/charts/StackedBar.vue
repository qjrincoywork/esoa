<script setup lang="ts">
import { computed } from 'vue';
import { formatNumber } from './format';
import { toneVar } from './tokens';
import type { ChartDatum } from './types';

/**
 * Part-to-whole as one stacked bar, with every part listed beneath it.
 *
 * The compact form of "what is this total made of": it fits inside a stat tile where a
 * donut would not, and reads in one glance left to right. Segments are separated by a
 * 2px gap in the surface rather than a stroke, the bar's ends are rounded once for the
 * whole, and every segment's value and share are written out in the list — the bar is
 * the summary, never the only copy of a number.
 */
const props = withDefaults(
    defineProps<{
        items: ChartDatum[];
        /** Names the whole for screen readers, e.g. "Outstanding balance by status". */
        label: string;
        /** List rows become buttons that emit `select`. */
        clickable?: boolean;
    }>(),
    { clickable: false },
);

const emit = defineEmits<{ select: [item: ChartDatum] }>();

const total = computed(() =>
    props.items.reduce((sum, item) => sum + Math.max(item.value, 0), 0),
);

const share = (value: number): number =>
    total.value > 0 ? (Math.max(value, 0) / total.value) * 100 : 0;

const shareLabel = (value: number): string => {
    const percent = share(value);

    // A present-but-tiny part says so rather than rounding down to a misleading 0%.
    return percent > 0 && percent < 1 ? '<1%' : `${Math.round(percent)}%`;
};

/** Only parts that exist are drawn; a zero would still cost a gap. */
const segments = computed(() => props.items.filter((item) => item.value > 0));

const valueLabel = (item: ChartDatum): string =>
    item.valueLabel ?? formatNumber(item.value);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            class="flex h-2.5 w-full gap-0.5 overflow-hidden rounded-[4px]"
            :style="{ backgroundColor: total > 0 ? undefined : 'var(--viz-grid)' }"
            role="img"
            :aria-label="label"
        >
            <div
                v-for="segment in segments"
                :key="segment.key"
                class="h-full min-w-0.5 transition-[flex-grow] duration-300"
                :style="{
                    flexGrow: segment.value,
                    flexBasis: 0,
                    backgroundColor: toneVar(segment.tone ?? 'series-1'),
                }"
                :title="`${segment.label}: ${valueLabel(segment)} (${shareLabel(segment.value)})`"
            />
        </div>

        <ul class="flex flex-col gap-0.5">
            <li v-for="item in items" :key="item.key">
                <component
                    :is="clickable ? 'button' : 'div'"
                    :type="clickable ? 'button' : undefined"
                    :class="[
                        'flex w-full items-center gap-2 rounded-md px-1.5 py-1 text-left text-sm',
                        clickable
                            ? 'cursor-pointer transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                            : '',
                    ]"
                    @click="clickable && emit('select', item)"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-[2px]"
                        :style="{ backgroundColor: toneVar(item.tone ?? 'series-1') }"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1 truncate text-muted-foreground">
                        {{ item.label }}
                        <span v-if="item.secondaryLabel" class="text-xs">
                            · {{ item.secondaryLabel }}
                        </span>
                    </span>
                    <span class="shrink-0 font-semibold text-foreground tabular-nums">
                        {{ valueLabel(item) }}
                    </span>
                    <span
                        class="w-9 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                    >
                        {{ shareLabel(item.value) }}
                    </span>
                </component>
            </li>
        </ul>
    </div>
</template>
