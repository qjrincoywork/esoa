<script setup lang="ts">
/**
 * One audit entry, opened up.
 *
 * The change table is the point of it — old value beside new, one row per field —
 * with the request it arrived on underneath. The same component serves the detail
 * pane's first tab and the top pane a batch entry opens in, so an entry reads the
 * same way wherever it was reached from.
 */
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { ArrowRight, Info } from 'lucide-vue-next';
import { eventClass, type ActivityLogDetail, type ActivityLogRow } from '@/composables/activityLogs';

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
const context = computed(() => props.detail?.context ?? null);

/** The facts worth a row each; blanks are dropped rather than shown empty. */
const summaryRows = computed(() =>
  [
    { label: 'When', value: shown.value?.logged_at },
    { label: 'Module', value: shown.value?.module },
    { label: 'Changed by', value: shown.value?.causer ?? 'System' },
    { label: 'Record', value: shown.value?.subject_type ? `${shown.value.subject_type} #${shown.value.subject_id}` : null },
    { label: 'Email', value: props.detail?.causer_email },
  ].filter((r) => r.value !== null && r.value !== undefined && r.value !== ''),
);

const contextRows = computed(() =>
  [
    { label: 'IP address', value: context.value?.ip },
    { label: 'Route', value: context.value?.route },
    { label: 'Method', value: context.value?.method },
    { label: 'User agent', value: context.value?.user_agent },
  ].filter((r) => r.value),
);
</script>

<template>
  <div class="flex w-full flex-col gap-4">
    <!-- What happened -->
    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap items-center gap-2">
        <Badge :class="eventClass(shown?.event)" class="border-transparent">
          {{ shown?.event_label ?? 'Activity' }}
        </Badge>
        <Badge variant="outline">{{ shown?.module }}</Badge>
        <Badge v-if="shown?.change_count" variant="secondary">
          {{ shown.change_count }} {{ shown.change_count === 1 ? 'field' : 'fields' }}
        </Badge>
      </div>
      <p class="text-sm font-medium">{{ shown?.description }}</p>
    </div>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
      <div
        v-for="row in summaryRows"
        :key="row.label"
        class="flex flex-col border-b border-[var(--color-border)] py-1.5 last:border-0">
        <dt class="text-xs text-[var(--color-text-muted)]">{{ row.label }}</dt>
        <dd class="truncate text-sm" :title="String(row.value)">{{ row.value }}</dd>
      </div>
    </dl>

    <!-- What changed -->
    <section>
      <h4 class="mb-1.5 text-sm font-medium">What changed</h4>

      <div v-if="changes.length" class="overflow-hidden rounded-lg border border-[var(--color-border)]">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-muted)]">
                <th class="px-3 py-2 text-left font-medium">Field</th>
                <th class="px-3 py-2 text-left font-medium">From</th>
                <th class="px-3 py-2 text-left font-medium">To</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="change in changes"
                :key="change.field"
                class="border-b border-[var(--color-border)] last:border-0 align-top">
                <td class="px-3 py-2 font-medium">{{ change.label }}</td>
                <td class="px-3 py-2 text-[var(--color-text-muted)]">
                  <span class="break-all">{{ change.from ?? '—' }}</span>
                </td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-start gap-1">
                    <ArrowRight class="mt-1 h-3 w-3 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />
                    <span class="break-all">{{ change.to ?? '—' }}</span>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <p v-else class="text-sm text-[var(--color-text-muted)]">
        No field-level changes were recorded for this entry.
      </p>
    </section>

    <!-- Where it came from -->
    <section v-if="contextRows.length">
      <h4 class="mb-1.5 text-sm font-medium">Request</h4>
      <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
        <div
          v-for="row in contextRows"
          :key="row.label"
          class="flex flex-col border-b border-[var(--color-border)] py-1.5 last:border-0">
          <dt class="text-xs text-[var(--color-text-muted)]">{{ row.label }}</dt>
          <dd class="truncate text-sm" :title="String(row.value)">{{ row.value }}</dd>
        </div>
      </dl>
    </section>
    <div
      v-else
      class="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-3 py-2 text-xs text-[var(--color-text-muted)]">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>No request was recorded — this change came from a console command or a queued job.</span>
    </div>
  </div>
</template>
