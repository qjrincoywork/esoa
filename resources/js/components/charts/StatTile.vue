<script setup lang="ts">
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { DashboardStat } from '@/types';
import { CircleCheck, Info, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { compactCurrency, compactNumber } from './format';
import { toneVar } from './tokens';

/**
 * A headline number — the right form when the data is one value, where a one-bar bar
 * chart would only add chrome.
 *
 * The tone is paired with an icon and a label, never carried by color alone: a status
 * color on its own is unreadable for part of the audience and meaningless in print.
 * Values use proportional figures (tabular digits make a large number look loose) and are
 * compacted only when they would otherwise wrap — the exact figure stays in the hint and
 * in the underlying table.
 */
const props = withDefaults(
    defineProps<{
        stat: DashboardStat;
        /** The single number the dashboard leads with; rendered at hero size. */
        hero?: boolean;
        class?: string;
    }>(),
    { hero: false },
);

/** Compact only what would overflow: hero and currency values, never small counts. */
const displayValue = computed(() => {
    const { format, value, formatted } = props.stat;

    if (format === 'currency') {
        return Math.abs(value) >= 1_000_000
            ? compactCurrency(value)
            : formatted;
    }

    if (format === 'number') {
        return Math.abs(value) >= 100_000 ? compactNumber(value) : formatted;
    }

    return formatted;
});

/** Exact value, shown next to a compacted one so nothing is lost to rounding. */
const exactValue = computed(() =>
    displayValue.value === props.stat.formatted ? null : props.stat.formatted,
);

const toneIcon = computed(() => {
    switch (props.stat.tone) {
        case 'critical':
        case 'serious':
            return TriangleAlert;
        case 'good':
            return CircleCheck;
        case 'warning':
            return Info;
        default:
            return null;
    }
});
</script>

<template>
    <Card :class="cn('gap-0 px-4 py-4', props.class)">
        <div class="flex items-start justify-between gap-2">
            <p class="text-xs font-medium text-muted-foreground">
                {{ stat.label }}
            </p>
            <component
                :is="toneIcon"
                v-if="toneIcon"
                class="size-4 shrink-0"
                :style="{ color: toneVar(stat.tone) }"
                aria-hidden="true"
            />
        </div>

        <p
            :class="
                cn(
                    'mt-2 font-semibold tracking-tight text-foreground',
                    hero ? 'text-4xl md:text-5xl' : 'text-2xl',
                )
            "
        >
            {{ displayValue }}
        </p>

        <p v-if="exactValue" class="mt-1 text-xs text-muted-foreground">
            {{ exactValue }}
        </p>
        <p v-if="stat.hint" class="mt-1 text-xs text-muted-foreground">
            {{ stat.hint }}
        </p>

        <slot />
    </Card>
</template>
