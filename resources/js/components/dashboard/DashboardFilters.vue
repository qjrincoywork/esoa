<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Label } from '@/components/ui/label';
import {
    SearchableCombobox,
    type SearchableComboboxItem,
} from '@/components/ui/searchable-combobox';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ALL_USERS, type DashboardFilterPatch } from '@/composables/dashboard';
import { debounce } from '@/composables/utilities/helper';
import type { DashboardFilterOptions, DashboardFilters } from '@/types';
import { CalendarRange, RotateCcw, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * The one filter row of the dashboard.
 *
 * Filters live above the content they scope — never inside a card and never per chart — so
 * every widget below re-renders against the same slice and the figures always agree. Range
 * presets come first because that is the control readers reach for; the custom range hides
 * behind the "Custom range" option rather than competing with the presets.
 */
const props = defineProps<{
    filters: DashboardFilters;
    options: DashboardFilterOptions;
    /** Only staff roles may narrow the dashboard to another user's records. */
    canSelectUser: boolean;
    isCustomRange: boolean;
    isFiltered: boolean;
    processing: boolean;
}>();

const emit = defineEmits<{
    apply: [patch: DashboardFilterPatch];
    reset: [];
}>();

const CUSTOM_RANGE = 'custom';

/** Local (not UTC) `YYYY-MM-DD`, the format the native date input speaks. */
const toIsoDate = (date: Date): string =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

/**
 * Seed for the custom-range inputs while the active filter is unbounded ("All time"), so
 * switching to a custom range starts from a sane month instead of two empty inputs.
 */
const seedRange = (): { from: string; to: string } => {
    const today = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 29);

    return { from: toIsoDate(start), to: toIsoDate(today) };
};

const seed = seedRange();
const dateFrom = ref(props.filters.date_from ?? seed.from);
const dateTo = ref(props.filters.date_to ?? seed.to);

watch(
    () => [props.filters.date_from, props.filters.date_to] as const,
    ([from, to]) => {
        if (from && to) {
            dateFrom.value = from;
            dateTo.value = to;
        }
    },
);

const presetItems = computed(() => [
    ...props.options.presets,
    { value: CUSTOM_RANGE, name: 'Custom range' },
]);

const presetModel = computed({
    get: () => (props.isCustomRange ? CUSTOM_RANGE : props.filters.preset),
    set: (value: string) => {
        if (value === CUSTOM_RANGE) {
            // Seed the custom range with the range already on screen, so switching never
            // shows an empty chart while the reader picks dates.
            emit('apply', { date_from: dateFrom.value, date_to: dateTo.value });
            return;
        }

        emit('apply', { preset: value, date_from: null, date_to: null });
    },
});

const userItems = computed<SearchableComboboxItem[]>(() => [
    { value: ALL_USERS, name: 'All users' },
    ...props.options.users,
]);

const userModel = computed({
    get: () => props.filters.user_id ?? ALL_USERS,
    set: (value: string | number | (string | number)[] | null) => {
        const parsed = Number(Array.isArray(value) ? value[0] : value);
        emit('apply', {
            user_id:
                Number.isNaN(parsed) || parsed === ALL_USERS ? null : parsed,
        });
    },
});

const userSearch = ref('');

/** Date inputs fire on every keystroke; only chase the server once the pair settles. */
const applyRange = debounce(() => {
    if (dateFrom.value && dateTo.value) {
        emit('apply', { date_from: dateFrom.value, date_to: dateTo.value });
    }
}, 500);

watch([dateFrom, dateTo], () => applyRange());
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid w-full gap-2 sm:w-52">
                <Label
                    for="dashboard-range"
                    class="text-xs text-muted-foreground"
                >
                    <CalendarRange
                        class="mr-1 inline size-3.5"
                        aria-hidden="true"
                    />Period
                </Label>
                <Select v-model="presetModel">
                    <!-- The id lives on the trigger, which is the element the label points at. -->
                    <SelectTrigger id="dashboard-range" class="w-full">
                        <SelectValue placeholder="Select a period" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem
                                v-for="preset in presetItems"
                                :key="preset.value"
                                :value="preset.value"
                            >
                                {{ preset.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            <DateRangePicker
                v-if="isCustomRange"
                v-model:from="dateFrom"
                v-model:to="dateTo"
                id="dashboard-dates"
                label="Custom range"
                class="w-full sm:w-72 [&>label]:text-xs [&>label]:text-muted-foreground"
            />

            <div v-if="canSelectUser" class="grid w-full gap-2 sm:w-72">
                <Label
                    for="dashboard-user"
                    class="text-xs text-muted-foreground"
                >
                    <UserRound
                        class="mr-1 inline size-3.5"
                        aria-hidden="true"
                    />Viewing data of
                </Label>
                <SearchableCombobox
                    id="dashboard-user"
                    v-model="userModel"
                    v-model:search="userSearch"
                    :items="userItems"
                    placeholder="All users"
                    search-placeholder="Search user..."
                    empty-text="No user found."
                />
            </div>

            <Button
                v-if="isFiltered"
                type="button"
                variant="outline"
                class="sm:ml-auto"
                :disabled="processing"
                @click="emit('reset')"
            >
                <RotateCcw class="size-4" aria-hidden="true" />
                Reset filters
            </Button>
        </div>

        <p class="text-xs text-muted-foreground">
            Showing {{ filters.label }}
            <span aria-hidden="true">·</span>
            {{ filters.granularity === 'day' ? 'daily' : 'monthly' }} breakdown
            <template v-if="processing">
                <span aria-hidden="true">·</span> updating…</template
            >
        </p>
    </div>
</template>
