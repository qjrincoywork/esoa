<script setup lang="ts">
/**
 * One audit entry, opened up.
 *
 * Reads top to bottom the way the question is asked: what happened, who did it and
 * when, what it changed, and where it came from. The change table is shown only where
 * a change is the point — a sign-in changes no field, and saying so on every one is
 * noise — and the request, with the device behind it, closes the entry
 * ({@link ActivityLogRequestDetails}). The same component serves the detail pane's first
 * tab and the top pane an entry opens in elsewhere, so an entry reads the same way
 * wherever it was reached from.
 */
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import ActivityLogRequestDetails from '@/components/forms/activity_logs/ActivityLogRequestDetails.vue';
import { ArrowRight, Clock, FileText, UserRound } from 'lucide-vue-next';
import { eventClass, eventIcon, type ActivityLogDetail, type ActivityLogRow } from '@/composables/activityLogs';
import { timeAgo } from '@/lib/relativeTime';

const props = withDefaults(
  defineProps<{
    /** The table row the entry was opened from — shown while the detail loads. */
    row: ActivityLogRow;
    detail?: ActivityLogDetail | null;
  }>(),
  { detail: null },
);

// A pane reuses this component when another entry is opened, so everything reads
// through the props rather than a snapshot taken once in setup.

/** Prefer the fetched entry, fall back to the row so the pane is never empty. */
const shown = computed(() => props.detail ?? (props.row as unknown as ActivityLogDetail));

const changes = computed(() => props.detail?.changes ?? []);

/** Whether to say anything about changes: always when there are some, else only where they were expected. */
const showChanges = computed(() => changes.value.length > 0 || props.detail?.records_changes !== false);

const loggedAgo = computed(() => (shown.value?.logged_at_value ? timeAgo(shown.value.logged_at_value) : null));

/**
 * Who, when and to what — a tile each, kept as data so the layout stays one loop.
 * The email sits under the name only when it says something the name does not.
 */
const overview = computed(() => {
  const entry = shown.value;
  const email = props.detail?.causer_email;

  return [
    {
      key: 'causer',
      label: 'Performed by',
      icon: UserRound,
      value: entry?.causer ?? 'System',
      hint: email && email !== entry?.causer ? email : null,
    },
    {
      key: 'when',
      label: 'When',
      icon: Clock,
      value: entry?.logged_at,
      hint: loggedAgo.value,
    },
    {
      key: 'record',
      label: 'Record',
      icon: FileText,
      value: entry?.subject_type ? `${entry.subject_type} #${entry.subject_id}` : null,
      hint: null,
    },
  ].filter((tile) => tile.value);
});
</script>

<template>
  <div class="flex w-full flex-col gap-5">
    <!-- What happened -->
    <div class="flex items-start gap-3">
      <div
        class="flex size-10 shrink-0 items-center justify-center rounded-full"
        :class="eventClass(shown?.event)">
        <component :is="eventIcon(shown?.event)" class="size-5" aria-hidden="true" />
      </div>
      <div class="flex min-w-0 flex-col gap-1.5">
        <p class="text-base leading-snug font-semibold break-words">{{ shown?.description }}</p>
        <div class="flex flex-wrap items-center gap-1.5">
          <Badge :class="eventClass(shown?.event)" class="border-transparent">
            {{ shown?.event_label ?? 'Activity' }}
          </Badge>
          <Badge variant="outline">{{ shown?.module }}</Badge>
          <Badge v-if="shown?.change_count" variant="secondary">
            {{ shown.change_count }} {{ shown.change_count === 1 ? 'field' : 'fields' }}
          </Badge>
          <span v-if="loggedAgo" class="text-xs text-muted-foreground" :title="shown?.logged_at ?? undefined">
            · {{ loggedAgo }}
          </span>
        </div>
      </div>
    </div>

    <!-- Who, when, to what -->
    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
      <div v-for="tile in overview" :key="tile.key" class="flex min-w-0 items-start gap-2.5 rounded-lg border bg-card p-3">
        <component :is="tile.icon" class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <div class="flex min-w-0 flex-col">
          <dt class="text-xs text-muted-foreground">{{ tile.label }}</dt>
          <dd class="text-sm font-medium break-words">{{ tile.value }}</dd>
          <dd v-if="tile.hint" class="truncate text-xs text-muted-foreground" :title="tile.hint">{{ tile.hint }}</dd>
        </div>
      </div>
    </dl>

    <!-- What changed -->
    <div v-if="showChanges" class="flex flex-col gap-2">
      <h4 class="text-sm font-semibold">
        What changed
        <span v-if="changes.length" class="font-normal text-muted-foreground">({{ changes.length }})</span>
      </h4>

      <div v-if="changes.length" class="overflow-hidden rounded-lg border">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-muted/50 text-xs text-muted-foreground">
                <th class="px-3 py-2 text-left font-medium">Field</th>
                <th class="px-3 py-2 text-left font-medium">From</th>
                <th class="px-3 py-2 text-left font-medium">To</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="change in changes" :key="change.field" class="border-b align-top last:border-0">
                <td class="px-3 py-2 font-medium whitespace-nowrap">{{ change.label }}</td>
                <td class="px-3 py-2 text-muted-foreground">
                  <span class="break-all">{{ change.from ?? '—' }}</span>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-start gap-1.5">
                    <ArrowRight class="mt-1 size-3 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="font-medium break-all">{{ change.to ?? '—' }}</span>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-else class="rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground">
        No field-level changes were recorded for this entry.
      </p>
    </div>

    <!-- Where it came from — only once the detail has arrived, since the row carries no request -->
    <ActivityLogRequestDetails v-if="detail" :context="detail.context" :device="detail.device" />
  </div>
</template>
