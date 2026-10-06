<script setup lang="ts">
/**
 * The user pane's Activity tab: everything one user has done, newest first.
 *
 * It is the audit trail pinned to this user as the one who acted — record changes in
 * the audited modules as well as their sign-ins, sign-outs and password changes — so
 * it is filtered the same way the trail is (module, event, dates, search) and an entry
 * opens in the same detail view, over the pane, leaving the list where it was.
 *
 * The paging and the rows are {@link ActivityLogFeed}'s; this only decides which slice
 * of the trail to page through. The filter choices arrive with the first page rather
 * than with the pane, so a pane opened only to read details never asks for them.
 */
import { computed, ref, watch } from 'vue';
import { refDebounced } from '@vueuse/core';
import ActivityLogFeed from '@/components/forms/activity_logs/ActivityLogFeed.vue';
import FilterSelect from '@/components/FilterSelect.vue';
import ListSearch from '@/components/ListSearch.vue';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import type { ActivityLogPageFetcher } from '@/composables/activityLogs';
import { useUsers, type UserActivityLogPayload, type UserActivityLogParams } from '@/composables/users';
import { X } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    userId: number | string;
    perPage?: number;
  }>(),
  { perPage: 10 },
);

const { getUserActivityLogs } = useUsers();

type Filters = Required<Pick<UserActivityLogParams, 'log_name' | 'event' | 'date_from' | 'date_to'>>;

const emptyFilters = (): Filters => ({ log_name: '', event: '', date_from: '', date_to: '' });

const filters = ref<Filters>(emptyFilters());
const search = ref('');
// Typing should not fire a request per keystroke; every other filter applies at once.
const debouncedSearch = refDebounced(search, 400);

const filterOptions = ref<UserActivityLogPayload['filter_options'] | null>(null);

const toSelectOptions = (options: Array<{ value: string; name: string }> = []) =>
  options.map(({ value, name }) => ({ value: String(value), label: name }));

/** The dropdowns, kept as data so a new filter is one entry rather than more markup. */
const selects = computed(() => [
  { key: 'log_name' as const, label: 'Module', allLabel: 'All modules', options: toSelectOptions(filterOptions.value?.modules) },
  { key: 'event' as const, label: 'Event', allLabel: 'All events', options: toSelectOptions(filterOptions.value?.events) },
]);

const isNarrowed = computed(
  () => search.value.trim() !== '' || Object.values(filters.value).some((value) => value !== ''),
);

const clearFilters = () => {
  filters.value = emptyFilters();
  search.value = '';
};

// Another user is another trail: what was narrowed for the last one does not carry over.
watch(() => props.userId, clearFilters);

/**
 * The query as it should be sent: the debounced search, and a date range put the right
 * way round, since the server rejects a range that ends before it starts.
 */
const query = computed<UserActivityLogParams>(() => {
  const { date_from, date_to, ...rest } = filters.value;
  const [from, to] = date_from && date_to && date_from > date_to ? [date_to, date_from] : [date_from, date_to];

  return { ...rest, date_from: from, date_to: to, search_string: debouncedSearch.value.trim() };
});

/**
 * A new fetcher whenever the user or the query changes, which is what tells the feed
 * to start over from page one.
 */
const fetchPage = computed<ActivityLogPageFetcher>(() => {
  const userId = props.userId;
  const params = query.value;

  return async (page) => {
    const payload = await getUserActivityLogs(userId, { ...params, ...page });

    if (payload?.filter_options) filterOptions.value = payload.filter_options;

    return payload?.activity_logs ?? null;
  };
});
</script>

<template>
  <div class="flex w-full flex-col gap-3">
    <div class="flex flex-col gap-2">
      <ListSearch
        id="user-activity-search"
        v-model="search"
        class="sm:max-w-none"
        label="Search this user's activity"
        placeholder="Search description or record…" />

      <div class="flex flex-wrap items-center gap-2">
        <FilterSelect
          v-for="select in selects"
          :key="select.key"
          v-model="filters[select.key]"
          class="w-40"
          :label="select.label"
          :all-label="select.allLabel"
          :options="select.options" />

        <DateRangePicker
          id="user-activity-range"
          class="w-72"
          v-model:from="filters.date_from"
          v-model:to="filters.date_to" />

        <Button
          v-if="isNarrowed"
          variant="ghost"
          size="sm"
          class="h-9 cursor-pointer text-muted-foreground"
          @click="clearFilters">
          <X class="size-4" aria-hidden="true" />
          Clear
        </Button>
      </div>
    </div>

    <ActivityLogFeed
      :fetch-page="fetchPage"
      :per-page="props.perPage"
      show-module
      :empty-text="isNarrowed ? 'No activity matches these filters.' : 'This user has no recorded activity yet.'"
      failed-text="This user's activity could not be loaded." />
  </div>
</template>
