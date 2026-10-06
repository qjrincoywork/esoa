<script setup lang="ts">
/**
 * The rest of the action an entry was part of, a page at a time.
 *
 * One action routinely writes more than one entry, and a batch upload writes one per
 * row of the file — so the batch is paged from the server rather than carried by the
 * entry that opened it, and a row opens its own changes in the top pane so the reader
 * keeps their place in the list underneath.
 */
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-vue-next';
import { eventClass, useActivityLogs, type ActivityLogRow } from '@/composables/activityLogs';

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

const { getBatchSiblings, openActivityLogChanges } = useActivityLogs();

const rows = ref<ActivityLogRow[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(props.total);
const from = ref<number | null>(null);
const to = ref<number | null>(null);
const loading = ref(false);
const failed = ref(false);

/** Fetch one page; the server's own numbers are what the footer then reports. */
const load = async (target: number) => {
  loading.value = true;
  failed.value = false;

  const result = await getBatchSiblings(props.logId, { page: target, per_page: props.perPage });

  if (result) {
    rows.value = result.data ?? [];
    page.value = result.current_page;
    lastPage.value = Math.max(1, result.last_page);
    total.value = result.total;
    from.value = result.from;
    to.value = result.to;
  } else {
    failed.value = true;
    rows.value = [];
  }

  loading.value = false;
};

// The first page is fetched when the tab is first opened, since that is when this
// component mounts; a different entry means a different batch, so it starts over.
watch(() => props.logId, () => load(1), { immediate: true });

/** Paging is one request at a time — the footer is disabled while one is in flight. */
const goTo = (target: number) => {
  if (loading.value || target < 1 || target > lastPage.value) return;

  load(target);
};

/** Only ever read beside rows, so the server's own numbers say it all. */
const rangeLabel = computed(() => `${from.value ?? 0}–${to.value ?? 0} of ${total.value}`);
</script>

<template>
  <div class="flex w-full flex-col gap-2">
    <p v-if="failed" class="text-sm text-[var(--color-text-muted)]">
      The rest of this action could not be loaded.
    </p>

    <div v-else class="overflow-hidden rounded-lg border border-[var(--color-border)]">
      <!-- Rows stay put while the next page is on its way, so the pane does not jump. -->
      <ul
        class="flex flex-col transition-opacity"
        :class="loading ? 'pointer-events-none opacity-50' : ''">
        <li
          v-for="sibling in rows"
          :key="sibling.id"
          class="border-b border-[var(--color-border)] last:border-0">
          <button
            type="button"
            class="flex w-full flex-col gap-1 px-3 py-2 text-left transition-colors hover:bg-[var(--color-surface)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary-color)]"
            @click="openActivityLogChanges(sibling)">
            <span class="flex items-center gap-1.5">
              <Badge :class="eventClass(sibling.event)" class="shrink-0 border-transparent">
                {{ sibling.event_label }}
              </Badge>
              <span class="truncate text-sm">{{ sibling.description }}</span>
            </span>
            <span class="flex flex-wrap items-center gap-x-2 text-xs text-[var(--color-text-muted)]">
              <span>{{ sibling.logged_at }}</span>
              <span v-if="sibling.change_count">
                · {{ sibling.change_count }} {{ sibling.change_count === 1 ? 'field' : 'fields' }}
              </span>
            </span>
          </button>
        </li>

        <li v-if="!rows.length" class="px-3 py-6 text-center text-sm text-[var(--color-text-muted)]">
          <span v-if="loading" class="inline-flex items-center gap-1.5">
            <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
            Loading…
          </span>
          <span v-else>This change was not part of a larger action.</span>
        </li>
      </ul>

      <div
        v-if="rows.length"
        class="flex items-center justify-between gap-2 border-t border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5 text-xs text-[var(--color-text-muted)]">
        <span>{{ rangeLabel }}</span>

        <div class="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            class="h-7 w-7 p-0"
            :disabled="loading || page <= 1"
            @click="goTo(page - 1)">
            <ChevronLeft class="h-3.5 w-3.5" aria-hidden="true" />
            <span class="sr-only">Previous page</span>
          </Button>
          <span class="px-1">Page {{ page }} of {{ lastPage }}</span>
          <Button
            variant="outline"
            size="sm"
            class="h-7 w-7 p-0"
            :disabled="loading || page >= lastPage"
            @click="goTo(page + 1)">
            <ChevronRight class="h-3.5 w-3.5" aria-hidden="true" />
            <span class="sr-only">Next page</span>
          </Button>
        </div>
      </div>
    </div>

    <p v-if="rows.length" class="text-xs text-[var(--color-text-muted)]">
      Select an entry to see what changed in it.
    </p>
  </div>
</template>
