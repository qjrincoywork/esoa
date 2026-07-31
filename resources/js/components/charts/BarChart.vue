<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { VizTone } from '@/types';
import { computed } from 'vue';
import { formatNumber } from './format';
import { toneVar } from './tokens';
import type { ChartDatum } from './types';

/**
 * Horizontal bar chart, the default form for comparing magnitude across named categories.
 *
 * Horizontal rather than columnar because the categories here carry long names (account
 * names, aging buckets, usernames) — rotated column labels are the usual way that goes
 * wrong. Every bar is directly labelled with its value, which is also what licenses the
 * lighter marks of the palette: the number never depends on reading the color.
 *
 * Color follows the entity, not the row: a bar keeps its tone when filtering removes its
 * neighbours, and bars that are context rather than the point wear the de-emphasis gray.
 */
const props = withDefaults(
    defineProps<{
        items: ChartDatum[];
        /** Tone for the marks that carry the story. */
        accentTone?: VizTone;
        /** Tone for the marks that are context only (`emphasis: false`). */
        contextTone?: VizTone;
        /** Rows become buttons that emit `select`. */
        clickable?: boolean;
        /** Second line under the bar (e.g. the amount behind a count). */
        showSecondary?: boolean;
    }>(),
    {
        accentTone: 'series-1',
        contextTone: 'muted',
        clickable: false,
        showSecondary: true,
    },
);

const emit = defineEmits<{ select: [item: ChartDatum] }>();

const max = computed(() =>
    props.items.reduce((highest, item) => Math.max(highest, item.value), 0),
);

const barWidth = (value: number): string => {
    if (max.value <= 0 || value <= 0) {
        return '0%';
    }

    // Floor at a visible sliver so a small-but-present value never reads as zero.
    return `${Math.max((value / max.value) * 100, 1.5)}%`;
};

const barTone = (item: ChartDatum): VizTone => {
    if (item.tone) {
        return item.tone;
    }

    return item.emphasis === false ? props.contextTone : props.accentTone;
};

const valueLabel = (item: ChartDatum): string =>
    item.valueLabel ?? formatNumber(item.value);

const rowTitle = (item: ChartDatum): string =>
    [item.label, valueLabel(item), item.secondaryLabel]
        .filter(Boolean)
        .join(' · ');

const onSelect = (item: ChartDatum) => {
    if (props.clickable) {
        emit('select', item);
    }
};
</script>

<template>
    <TooltipProvider :delay-duration="120">
        <ul class="flex flex-col gap-1">
            <li v-for="item in items" :key="item.key">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <component
                            :is="clickable ? 'button' : 'div'"
                            :type="clickable ? 'button' : undefined"
                            :title="rowTitle(item)"
                            :class="[
                                'block w-full rounded-md px-2 py-2 text-left transition-colors',
                                clickable
                                    ? 'cursor-pointer hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                                    : '',
                            ]"
                            @click="onSelect(item)"
                        >
                            <div
                                class="flex items-baseline justify-between gap-3"
                            >
                                <span
                                    class="min-w-0 truncate text-sm text-foreground"
                                    >{{ item.label }}</span
                                >
                                <span
                                    class="shrink-0 text-sm font-semibold text-foreground tabular-nums"
                                >
                                    {{ valueLabel(item) }}
                                </span>
                            </div>

                            <div class="mt-1.5 h-3 w-full">
                                <div
                                    class="h-3 rounded-r-[4px] transition-[width] duration-300"
                                    :style="{
                                        width: barWidth(item.value),
                                        backgroundColor: toneVar(barTone(item)),
                                    }"
                                />
                            </div>

                            <p
                                v-if="showSecondary && item.secondaryLabel"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ item.secondaryLabel }}
                            </p>
                        </component>
                    </TooltipTrigger>

                    <TooltipContent>
                        <p class="font-semibold">{{ valueLabel(item) }}</p>
                        <p>{{ item.label }}</p>
                        <p v-if="item.secondaryLabel">
                            {{ item.secondaryLabel }}
                        </p>
                        <p v-if="clickable" class="opacity-80">
                            Click to open the filtered list
                        </p>
                    </TooltipContent>
                </Tooltip>
            </li>
        </ul>
    </TooltipProvider>
</template>
