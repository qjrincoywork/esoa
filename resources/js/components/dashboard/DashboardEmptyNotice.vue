<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { DashboardDataWindow, DashboardFilters } from '@/types';
import { CalendarRange, Info, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Explains an empty dashboard instead of leaving a wall of zeros.
 *
 * A filter that matches nothing is a legitimate answer, but "0" repeated across six widgets
 * reads as a broken page. This says which of the two happened — the selected period holds
 * no invoices (and here is the period that does), or the reported user has none at all —
 * and offers the one click that fixes it.
 */
const props = defineProps<{
    filters: DashboardFilters;
    dataWindow: DashboardDataWindow | null;
    /** Name of the user the dashboard is currently reporting on, when any. */
    userName?: string | null;
}>();

const emit = defineEmits<{
    showAllTime: [];
    clearUser: [];
}>();

const hasPeriod = computed(() =>
    Boolean(props.filters.date_from && props.filters.date_to),
);
const hasUser = computed(() => props.filters.user_id !== null);

const subject = computed(() =>
    hasUser.value ? (props.userName ?? 'this user') : 'this scope',
);

const message = computed(() => {
    if (!props.dataWindow) {
        return `No billing invoices have been recorded for ${subject.value} yet.`;
    }

    if (hasPeriod.value) {
        return `No billing invoices for ${subject.value} between ${props.filters.label}.`;
    }

    return hasUser.value
        ? `No billing invoices are attributed to ${subject.value}.`
        : 'No billing invoices match the current filters.';
});
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-xl border border-dashed bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-start gap-3">
            <Info
                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div class="text-sm">
                <p class="font-medium text-foreground">{{ message }}</p>
                <p v-if="dataWindow" class="mt-0.5 text-muted-foreground">
                    <CalendarRange
                        class="mr-1 inline size-3.5"
                        aria-hidden="true"
                    />
                    Data is available from {{ dataWindow.label }}.
                </p>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            <Button
                v-if="hasPeriod && dataWindow"
                type="button"
                variant="outline"
                size="sm"
                @click="emit('showAllTime')"
            >
                <CalendarRange class="size-4" aria-hidden="true" />
                Show all time
            </Button>
            <Button
                v-if="hasUser"
                type="button"
                variant="outline"
                size="sm"
                @click="emit('clearUser')"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                Clear user filter
            </Button>
        </div>
    </div>
</template>
