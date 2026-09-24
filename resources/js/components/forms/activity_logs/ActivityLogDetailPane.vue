<script setup lang="ts">
/**
 * One audit entry, opened up — and the action it was part of, behind a second tab.
 *
 * A row in the table says that something changed; the first tab says what. The second
 * holds the rest of the batch, which for an upload runs to thousands of entries: it is
 * a listing in its own right, so it is paged and kept out of the way of the entry the
 * reader actually asked for.
 */
import { computed, ref, watch } from 'vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import ActivityLogBatchList from '@/components/forms/activity_logs/ActivityLogBatchList.vue';
import ActivityLogEntryDetails from '@/components/forms/activity_logs/ActivityLogEntryDetails.vue';
import { type ActivityLogDetail, type ActivityLogRow } from '@/composables/activityLogs';

const props = withDefaults(
  defineProps<{
    /** The table row the pane was opened from — shown while the detail loads. */
    row: ActivityLogRow;
    detail?: ActivityLogDetail | null;
  }>(),
  { detail: null },
);

const DETAILS_TAB = 'details';
const BATCH_TAB = 'batch';

const activeTab = ref(DETAILS_TAB);

/** Nothing to page through until the detail says how much the action wrote. */
const batchCount = computed(() => props.detail?.batch_sibling_count ?? 0);

/**
 * The batch is fetched the first time its tab is opened and kept afterwards, so
 * stepping back to the entry and returning does not lose the reader's page.
 */
const batchOpened = ref(false);

watch(activeTab, (tab) => {
  if (tab === BATCH_TAB) batchOpened.value = true;
});

// The pane reuses this component when another entry is opened, so setup runs once
// while the props change underneath: a new entry starts on its own details, with its
// own batch left unfetched until asked for.
watch(() => props.row.id, () => {
  activeTab.value = DETAILS_TAB;
  batchOpened.value = false;
});
</script>

<template>
  <!-- Hidden tabs keep their state rather than remounting; see `batchOpened`. -->
  <Tabs v-model="activeTab" :unmount-on-hide="false" class="flex w-full flex-col">
    <TabsList class="grid w-full grid-cols-2">
      <TabsTrigger :value="DETAILS_TAB">Details</TabsTrigger>
      <TabsTrigger :value="BATCH_TAB" :disabled="!batchCount">
        This action
        <span v-if="batchCount" class="font-normal">({{ batchCount }})</span>
      </TabsTrigger>
    </TabsList>

    <TabsContent :value="DETAILS_TAB" class="mt-3">
      <ActivityLogEntryDetails :row="row" :detail="detail" />
    </TabsContent>

    <TabsContent :value="BATCH_TAB" class="mt-3">
      <ActivityLogBatchList
        v-if="batchOpened"
        :log-id="row.id"
        :total="batchCount" />
    </TabsContent>
  </Tabs>
</template>
