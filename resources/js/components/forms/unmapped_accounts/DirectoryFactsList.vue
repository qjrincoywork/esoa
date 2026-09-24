<script setup lang="ts">
/**
 * A record laid out as label/value pairs — the pane's "Details" tab, and the top pane
 * any row of its lists opens, both render through this so they read identically.
 */
import type { Fact } from '@/lib/directoryFacts';

defineProps<{
  facts: Fact[];
}>();
</script>

<template>
  <dl class="divide-y divide-[var(--color-border)] text-sm">
    <div v-for="fact in facts" :key="fact.label" class="grid grid-cols-3 gap-3 py-2">
      <dt class="text-[var(--color-text-muted)]">{{ fact.label }}</dt>
      <dd class="col-span-2 break-words" :class="fact.mono ? 'font-mono text-xs' : ''">
        {{ fact.value || '—' }}
      </dd>
    </div>
  </dl>
</template>
