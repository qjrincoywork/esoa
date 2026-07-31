<script setup lang="ts">
import { toneVar } from './tokens';
import type { ChartLegendItem } from './types';

/**
 * Identity key for a chart with two or more series.
 *
 * Always present when more than one series is on screen — the reader must never have to
 * match colors from memory. The swatch mirrors the mark (a rect for bars and areas, a
 * short stroke for lines) and the text stays in text tokens: only the swatch carries the
 * series color, so a light hue is never asked to be legible as type.
 */
withDefaults(
    defineProps<{
        items: ChartLegendItem[];
        /** Right-align the value column when the legend doubles as a value list. */
        withValues?: boolean;
    }>(),
    { withValues: false },
);
</script>

<template>
    <ul class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        <li
            v-for="item in items"
            :key="item.key"
            class="flex min-w-0 items-center gap-2"
            :class="
                withValues
                    ? 'w-full justify-between sm:w-auto sm:justify-start'
                    : ''
            "
        >
            <span class="flex min-w-0 items-center gap-2">
                <span
                    v-if="item.shape === 'line'"
                    class="inline-block h-0.5 w-4 shrink-0 rounded-full"
                    :style="{ backgroundColor: toneVar(item.tone) }"
                    aria-hidden="true"
                />
                <span
                    v-else
                    class="inline-block size-2.5 shrink-0 rounded-[2px]"
                    :style="{ backgroundColor: toneVar(item.tone) }"
                    aria-hidden="true"
                />
                <span class="truncate text-muted-foreground">{{
                    item.label
                }}</span>
            </span>
            <span
                v-if="item.value"
                class="shrink-0 font-medium text-foreground tabular-nums"
            >
                {{ item.value }}
            </span>
        </li>
    </ul>
</template>
