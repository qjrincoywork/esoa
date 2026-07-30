<script setup lang="ts">
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { compactCurrency } from './format';
import {
    buildAreaPath,
    buildLinePath,
    labelIndexes,
    niceTicks,
    spreadX,
} from './geometry';
import { CHART_CHROME, toneVar } from './tokens';
import type { ChartSeriesDefinition, ChartSeriesPoint } from './types';

/**
 * Multi-series line chart for change over time.
 *
 * One y-axis, always: series that do not share a unit belong in separate charts, because
 * two scales on one plot invent a correlation the data does not contain. The hover layer
 * is a crosshair that snaps to the nearest period and reads out every series at once — the
 * reader aims at a date, never at a two-pixel line — and the same read-out is reachable
 * from the keyboard with the arrow keys.
 */
const props = withDefaults(
    defineProps<{
        points: ChartSeriesPoint[];
        series: ChartSeriesDefinition[];
        height?: number;
        /** Formats axis ticks and the end labels. */
        formatValue?: (value: number) => string;
        /** Axis description announced to assistive technology. */
        ariaLabel?: string;
    }>(),
    {
        height: 260,
        formatValue: (value: number) => compactCurrency(value),
        ariaLabel: 'Trend over time',
    },
);

const container = ref<HTMLElement | null>(null);
const { width: containerWidth } = useElementSize(container);
const activeIndex = ref<number | null>(null);

/** Right gutter reserves room for the direct end labels so they can never be clipped. */
const PADDING = { top: 16, right: 64, bottom: 26, left: 56 } as const;
const MAX_X_LABELS = 7;
/** Below this vertical distance end labels would collide; the legend carries them instead. */
const MIN_LABEL_GAP = 16;

/**
 * Read a series field off a point.
 *
 * Points only contract `key` and `label`; the numeric fields are named by the series
 * definitions, so they are read dynamically rather than baked into the point type.
 */
const fieldValue = (point: ChartSeriesPoint, field: string): unknown =>
    (point as unknown as Record<string, unknown>)[field];

const width = computed(() => Math.max(containerWidth.value, 0));
const plotWidth = computed(() =>
    Math.max(width.value - PADDING.left - PADDING.right, 0),
);
const plotHeight = computed(() =>
    Math.max(props.height - PADDING.top - PADDING.bottom, 0),
);

const maxValue = computed(() =>
    props.points.reduce((highest, point) => {
        const pointMax = props.series.reduce(
            (seriesMax, series) =>
                Math.max(seriesMax, Number(fieldValue(point, series.key) ?? 0)),
            0,
        );

        return Math.max(highest, pointMax);
    }, 0),
);

const ticks = computed(() => niceTicks(maxValue.value));
const scaleMax = computed(() => ticks.value[ticks.value.length - 1] || 1);

const xs = computed(() =>
    spreadX(props.points.length, PADDING.left, PADDING.left + plotWidth.value),
);

const y = (value: number): number =>
    PADDING.top +
    plotHeight.value -
    (Math.max(value, 0) / scaleMax.value) * plotHeight.value;

const renderable = computed(() => width.value > 0 && props.points.length > 0);

const lines = computed(() =>
    props.series.map((series) => {
        const coordinates = props.points.map((point, index) => ({
            x: xs.value[index] ?? 0,
            y: y(Number(fieldValue(point, series.key) ?? 0)),
        }));

        return {
            series,
            coordinates,
            path: buildLinePath(coordinates),
            // A single series gets an area wash; with two it would muddy the overlap.
            area:
                props.series.length === 1
                    ? buildAreaPath(coordinates, PADDING.top + plotHeight.value)
                    : '',
            last: coordinates[coordinates.length - 1],
            lastValue: Number(
                fieldValue(
                    props.points[props.points.length - 1] ?? {
                        key: '',
                        label: '',
                    },
                    series.key,
                ) ?? 0,
            ),
        };
    }),
);

/** End labels are dropped when the series converge — nudged labels detach from their line. */
const showEndLabels = computed(() => {
    if (!renderable.value) {
        return false;
    }

    const endYs = lines.value.map((line) => line.last?.y ?? 0);

    return endYs.every((value, index) =>
        endYs.every(
            (other, otherIndex) =>
                index === otherIndex ||
                Math.abs(value - other) >= MIN_LABEL_GAP,
        ),
    );
});

const xLabels = computed(() => {
    const indexes = labelIndexes(props.points.length, MAX_X_LABELS);

    return indexes.map((index) => ({
        index,
        x: xs.value[index] ?? 0,
        label: props.points[index]?.label ?? '',
    }));
});

const activePoint = computed(() =>
    activeIndex.value === null
        ? null
        : (props.points[activeIndex.value] ?? null),
);

const tooltipStyle = computed(() => {
    if (activeIndex.value === null) {
        return {};
    }

    const x = xs.value[activeIndex.value] ?? 0;
    const clamped = Math.min(Math.max(x, 90), Math.max(width.value - 90, 90));

    return { left: `${clamped}px`, top: `${PADDING.top}px` };
});

/** Formatted value for the tooltip; falls back to the axis formatter. */
const seriesValue = (
    series: ChartSeriesDefinition,
    point: ChartSeriesPoint,
): string => {
    const formatted = fieldValue(
        point,
        series.formattedField ?? `${series.key}_formatted`,
    );

    return typeof formatted === 'string'
        ? formatted
        : props.formatValue(Number(fieldValue(point, series.key) ?? 0));
};

/** Snap the crosshair to the nearest period rather than to the exact pointer position. */
const onPointerMove = (event: PointerEvent) => {
    if (!renderable.value || props.points.length === 0) {
        return;
    }

    const bounds = (
        event.currentTarget as SVGRectElement
    ).getBoundingClientRect();
    const offset = event.clientX - bounds.left - PADDING.left;
    const step =
        props.points.length > 1
            ? plotWidth.value / (props.points.length - 1)
            : plotWidth.value;
    const index = step > 0 ? Math.round(offset / step) : 0;

    activeIndex.value = Math.min(Math.max(index, 0), props.points.length - 1);
};

const onKeydown = (event: KeyboardEvent) => {
    if (props.points.length === 0) {
        return;
    }

    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const current =
            activeIndex.value ??
            (event.key === 'ArrowRight' ? -1 : props.points.length);
        const next = current + (event.key === 'ArrowRight' ? 1 : -1);
        activeIndex.value = Math.min(
            Math.max(next, 0),
            props.points.length - 1,
        );
        return;
    }

    if (event.key === 'Escape') {
        activeIndex.value = null;
    }
};
</script>

<template>
    <div ref="container" class="relative w-full">
        <svg
            v-if="renderable"
            :width="width"
            :height="height"
            :viewBox="`0 0 ${width} ${height}`"
            role="img"
            :aria-label="ariaLabel"
            tabindex="0"
            class="focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            @keydown="onKeydown"
            @blur="activeIndex = null"
        >
            <!-- Grid: solid hairlines one step off the surface, never dashed. -->
            <g>
                <line
                    v-for="tick in ticks"
                    :key="`grid-${tick}`"
                    :x1="PADDING.left"
                    :x2="PADDING.left + plotWidth"
                    :y1="y(tick)"
                    :y2="y(tick)"
                    :stroke="CHART_CHROME.grid"
                    stroke-width="1"
                    shape-rendering="crispEdges"
                />
                <text
                    v-for="tick in ticks"
                    :key="`tick-${tick}`"
                    :x="PADDING.left - 8"
                    :y="y(tick) + 3"
                    text-anchor="end"
                    font-size="10"
                    class="tabular-nums"
                    :fill="CHART_CHROME.axis"
                >
                    {{ formatValue(tick) }}
                </text>
            </g>

            <g>
                <text
                    v-for="label in xLabels"
                    :key="`x-${label.index}`"
                    :x="label.x"
                    :y="height - 8"
                    text-anchor="middle"
                    font-size="10"
                    :fill="CHART_CHROME.axis"
                >
                    {{ label.label }}
                </text>
            </g>

            <!-- Crosshair sits under the marks so it never covers a dot. -->
            <line
                v-if="activeIndex !== null"
                :x1="xs[activeIndex]"
                :x2="xs[activeIndex]"
                :y1="PADDING.top"
                :y2="PADDING.top + plotHeight"
                :stroke="CHART_CHROME.axis"
                stroke-width="1"
                opacity="0.5"
            />

            <g v-for="line in lines" :key="line.series.key">
                <path
                    v-if="line.area"
                    :d="line.area"
                    :fill="toneVar(line.series.token)"
                    fill-opacity="0.1"
                    stroke="none"
                />
                <path
                    :d="line.path"
                    fill="none"
                    :stroke="toneVar(line.series.token)"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <!-- End marker with a surface ring, so overlapping markers stay legible. -->
                <circle
                    v-if="line.last"
                    :cx="line.last.x"
                    :cy="line.last.y"
                    r="4"
                    :fill="toneVar(line.series.token)"
                    :stroke="CHART_CHROME.surface"
                    stroke-width="2"
                />
                <text
                    v-if="showEndLabels && line.last"
                    :x="line.last.x + 10"
                    :y="line.last.y + 3"
                    font-size="11"
                    font-weight="600"
                    class="tabular-nums"
                    fill="currentColor"
                >
                    {{ formatValue(line.lastValue) }}
                </text>

                <circle
                    v-if="activeIndex !== null && line.coordinates[activeIndex]"
                    :cx="line.coordinates[activeIndex].x"
                    :cy="line.coordinates[activeIndex].y"
                    r="4.5"
                    :fill="toneVar(line.series.token)"
                    :stroke="CHART_CHROME.surface"
                    stroke-width="2"
                />
            </g>

            <!-- Full-plot hit area: the pointer only has to be closest, never dead-centre. -->
            <rect
                :x="PADDING.left"
                :y="PADDING.top"
                :width="plotWidth"
                :height="plotHeight"
                fill="transparent"
                @pointermove="onPointerMove"
                @pointerleave="activeIndex = null"
            />
        </svg>

        <div
            v-if="activePoint"
            class="pointer-events-none absolute z-10 min-w-40 -translate-x-1/2 rounded-md border bg-popover px-3 py-2 text-xs shadow-md"
            :style="tooltipStyle"
            role="status"
        >
            <p class="mb-1 font-medium text-muted-foreground">
                {{ activePoint.label }}
            </p>
            <p
                v-for="series in props.series"
                :key="series.key"
                class="flex items-center justify-between gap-3"
            >
                <span class="flex items-center gap-1.5 text-muted-foreground">
                    <span
                        class="inline-block h-0.5 w-3 rounded-full"
                        :style="{ backgroundColor: toneVar(series.token) }"
                        aria-hidden="true"
                    />
                    {{ series.label }}
                </span>
                <span
                    class="font-semibold text-popover-foreground tabular-nums"
                >
                    {{ seriesValue(series, activePoint) }}
                </span>
            </p>
        </div>
    </div>
</template>
