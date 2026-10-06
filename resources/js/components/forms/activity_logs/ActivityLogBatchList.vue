<script setup lang="ts">
/**
 * The rest of the action an entry was part of, a page at a time.
 *
 * One action routinely writes more than one entry, and a batch upload writes one per
 * row of the file — so the batch is paged from the server rather than carried by the
 * entry that opened it. The paging and the rows themselves are {@link ActivityLogFeed}'s;
 * this only says which entries to page through.
 */
import { computed } from 'vue';
import ActivityLogFeed from '@/components/forms/activity_logs/ActivityLogFeed.vue';
import { useActivityLogs, type ActivityLogPageFetcher } from '@/composables/activityLogs';

const props = withDefaults(
  defineProps<{
    /** The entry the batch belongs to; its own row is never part of the list. */
    logId: number | string;
    /** What the detail said the batch holds, so the footer reads right before page one lands. */
    total?: number;
    perPage?: number;
  }>(),
  { total: 0, perPage: 10 },
);

const { getBatchSiblings } = useActivityLogs();

// A different entry means a different batch, so the fetcher follows the entry and the
// feed starts over whenever it changes.
const fetchPage = computed<ActivityLogPageFetcher>(() => {
  const logId = props.logId;

  return (params) => getBatchSiblings(logId, params);
});
</script>

<template>
  <ActivityLogFeed
    :fetch-page="fetchPage"
    :total="props.total"
    :per-page="props.perPage"
    empty-text="This change was not part of a larger action."
    failed-text="The rest of this action could not be loaded." />
</template>
