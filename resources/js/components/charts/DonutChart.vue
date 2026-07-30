<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatNumber } from './format';
import { donutSegments } from './geometry';
import { CHART_CHROME, toneVar } from './tokens';
import type { ChartDatum } from './types';

/**
 * Part-to-whole at a glance, for a handful of segments.
 *
 * Deliberately limited to the "share of the whole" job: comparing close values is a bar
 * chart's work, so every segment is also listed with its exact value beside the ring, and
 * the ring itself is only the summary. Segments are separated by a gap in the surface —
 * never by a stroke drawn around the mark — and hovering one reads it out in the middle
 * of the donut instead of relying on a floating tooltip.
 */
const props = withDefaults(
    defineProps<{
        items: ChartDatum[];
        /** Caption under the centre figure when nothing is hovered. */
        centerLabel?: string;
        /** Pre-formatted total; defaults to the summed values. */
        centerValue?: string;
        size?: number;
        thickness?: number;
        clickable?: boolean;
    }>(),
    {
        centerLabel: 'Total',
        size: 200,
        thickness: 22,
        clickable: false,
    },
);

const emit = defineEmits<{ select: [item: ChartDatum] }>();

const hoveredKey = ref<string | null>(null);

const radius = computed(() => (props.size - props.thickness) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const center = computed(() => props.size / 2);

const total = computed(() =>
    props.items.reduce((sum, item) => sum + Math.max(item.value, 0), 0),
);

const segments = computed(() => {
    const geometry = donutSegments(
        props.items.map((item) => item.value),
        circumference.value,
    );

    return props.items.map((item, index) => ({
        item,
        dash: geometry[index].dash,
        offset: geometry[index].offset,
    }));
});

const hovered = computed(
    () => props.items.find((item) => item.key === hoveredKey.value) ?? null,
);

const headline = computed(() => {
    if (hovered.value) {
        return hovered.value.valueLabel ?? formatNumber(hovered.value.value);
    }

    return props.centerValue ?? formatNumber(total.value);
});

const caption = computed(() => hovered.value?.label ?? props.centerLabel);

const share = (value: number): string =>
    total.value > 0 ? `${Math.round((value / total.value) * 100)}%` : '0%';
</script>

<template>
    <div
        class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6"
    >
        <div
            class="relative shrink-0"
            :style="{ width: `${size}px`, height: `${size}px` }"
        >
            <svg
                :width="size"
                :height="size"
                :viewBox="`0 0 ${size} ${size}`"
                role="img"
                :aria-label="`${caption}: ${headline}`"
            >
                <circle
                    v-if="total <= 0"
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    :stroke="CHART_CHROME.grid"
                    :stroke-width="thickness"
                />

                <circle
                    v-for="segment in segments"
                    v-else
                    :key="segment.item.key"
                    :cx="center"
                    :cy="center"
                    :r="radius"
                    fill="none"
                    :stroke="toneVar(segment.item.tone)"
                    :stroke-width="
                        hoveredKey === segment.item.key
                            ? thickness + 4
                            : thickness
                    "
                    :stroke-dasharray="`${segment.dash} ${circumference - segment.dash}`"
                    :stroke-dashoffset="segment.offset"
                    :transform="`rotate(-90 ${center} ${center})`"
                    class="transition-[stroke-width] duration-150"
                    @pointerenter="hoveredKey = segment.item.key"
                    @pointerleave="hoveredKey = null"
                >
                    <title>
                        {{ segment.item.label }}:
                        {{ segment.item.valueLabel ?? segment.item.value }}
                    </title>
                </circle>
            </svg>

            <div
                class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
            >
                <span
                    class="text-2xl font-semibold tracking-tight text-foreground"
                    >{{ headline }}</span
                >
                <span
                    class="mt-0.5 max-w-[70%] truncate text-xs text-muted-foreground"
                    >{{ caption }}</span
                >
            </div>
        </div>

        <ul class="w-full min-w-0 flex-1 space-y-1">
            <li v-for="item in items" :key="item.key">
                <component
                    :is="clickable ? 'button' : 'div'"
                    :type="clickable ? 'button' : undefined"
                    :class="[
                        'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors',
                        clickable
                            ? 'cursor-pointer hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none'
                            : '',
                        hoveredKey === item.key ? 'bg-muted' : '',
                    ]"
                    @pointerenter="hoveredKey = item.key"
                    @pointerleave="hoveredKey = null"
                    @focus="hoveredKey = item.key"
                    @blur="hoveredKey = null"
                    @click="clickable && emit('select', item)"
                >
                    <span
                        class="size-2.5 shrink-0 rounded-[2px]"
                        :style="{ backgroundColor: toneVar(item.tone) }"
                        aria-hidden="true"
                    />
                    <span
                        class="min-w-0 flex-1 truncate text-sm text-muted-foreground"
                        >{{ item.label }}</span
                    >
                    <span
                        class="shrink-0 text-sm font-semibold text-foreground tabular-nums"
                    >
                        {{ item.valueLabel ?? formatNumber(item.value) }}
                    </span>
                    <span
                        class="w-10 shrink-0 text-right text-xs text-muted-foreground tabular-nums"
                    >
                        {{ share(item.value) }}
                    </span>
                </component>
            </li>
        </ul>
    </div>
</template>
