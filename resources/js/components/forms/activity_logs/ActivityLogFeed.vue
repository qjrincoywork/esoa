<script setup lang="ts">
/**
 * A paged list of audit entries, fetched from the server a page at a time.
 *
 * Where the entries come from is the caller's business — the rest of one action, or
 * everything one user did — so it is handed in as a fetcher, and a new fetcher (another
 * entry, another user, different filters) starts the list over from page one. A row
 * opens its own changes in the top pane, so the reader keeps their place in the list
 * underneath.
 */
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-vue-next';
import {
  eventClass,
  useActivityLogs,
  type ActivityLogPageFetcher,
  type ActivityLogRow,
} from '@/composables/activityLogs';

const props = withDefaults(
  defineProps<{
    fetchPage: ActivityLogPageFetcher;
    /** What the caller already knows the list holds, so the footer reads right before page one lands. */
    total?: number;
    perPage?: number;
    /** Name each row's module — worth it when the rows are not all from one place. */
    showModule?: boolean;
    emptyText?: string;
    failedText?: string;
  }>(),
  {
    total: 0,
    perPage: 10,
    showModule: false,
    emptyText: 'Nothing to show.',
    failedText: 'These entries could not be loaded.',
  },
);

const { openActivityLogChanges } = useActivityLogs();

const rows = ref<ActivityLogRow[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(props.total);
const from = ref<number | null>(null);
const to = ref<number | null>(null);
const loading = ref(false);
const failed = ref(false);

/**
 * Only the latest request may land. Filters change faster than pages arrive, and an
 * older answer arriving last would otherwise show a page that was no longer asked for.
 */
let latestRequest = 0;

/** Fetch one page; the server's own numbers are what the footer then reports. */
const load = async (target: number) => {
  const request = ++latestRequest;

  loading.value = true;
  failed.value = false;

  const result = await props.fetchPage({ page: target, per_page: props.perPage });

  if (request !== latestRequest) return;

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

// The first page is fetched when the list first mounts; a new fetcher means a
// different list, so it starts over.
watch(() => props.fetchPage, () => load(1), { immediate: true });

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
    <p v-if="failed" class="text-sm text-[var(--color-text-muted)]">{{ failedText }}</p>

    <div v-else class="overflow-hidden rounded-lg border border-[var(--color-border)]">
      <!-- Rows stay put while the next page is on its way, so the pane does not jump. -->
      <ul
        class="flex flex-col transition-opacity"
        :class="loading ? 'pointer-events-none opacity-50' : ''"
        :aria-busy="loading">
        <li
          v-for="entry in rows"
          :key="entry.id"
          class="border-b border-[var(--color-border)] last:border-0">
          <button
            type="button"
            class="flex w-full cursor-pointer flex-col gap-1 px-3 py-2 text-left transition-colors hover:bg-[var(--color-surface)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--primary-color)]"
            @click="openActivityLogChanges(entry)">
            <span class="flex items-center gap-1.5">
              <Badge :class="eventClass(entry.event)" class="shrink-0 border-transparent">
                {{ entry.event_label ?? 'Activity' }}
              </Badge>
              <span class="truncate text-sm" :title="entry.description ?? undefined">{{ entry.description }}</span>
            </span>
            <span class="flex flex-wrap items-center gap-x-2 text-xs text-[var(--color-text-muted)]">
              <span :title="entry.logged_at_value ?? undefined">{{ entry.logged_at }}</span>
              <span v-if="showModule">· {{ entry.module }}</span>
              <span v-if="entry.change_count">
                · {{ entry.change_count }} {{ entry.change_count === 1 ? 'field' : 'fields' }}
              </span>
            </span>
          </button>
        </li>

        <li v-if="!rows.length" class="px-3 py-6 text-center text-sm text-[var(--color-text-muted)]">
          <span v-if="loading" class="inline-flex items-center gap-1.5">
            <Loader2 class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
            Loading…
          </span>
          <span v-else>{{ emptyText }}</span>
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
      Select an entry to see its details.
    </p>
  </div>
</template>
