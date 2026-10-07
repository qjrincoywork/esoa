<script setup lang="ts">
/**
 * Where an audit entry came from: the device, then the request it arrived on.
 *
 * The device leads because it is what a reader actually asks — "was this on their phone
 * or at a PC?" — and the server has already read it off the user agent, so the raw
 * agent is shown in full underneath as the evidence rather than as the answer. Values a
 * reader is likely to paste elsewhere (the address, the agent) can be copied in one click.
 */
import { computed, ref, watch, type Component } from 'vue';
import { useClipboard } from '@vueuse/core';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Bot, Check, CircleHelp, Copy, Info, Monitor, Smartphone, Tablet } from 'lucide-vue-next';
import type { ActivityLogContext, ActivityLogDevice } from '@/composables/activityLogs';

const props = withDefaults(
  defineProps<{
    context?: ActivityLogContext | null;
    device?: ActivityLogDevice | null;
  }>(),
  { context: null, device: null },
);

const DEVICE_ICONS: Record<ActivityLogDevice['device_type'], Component> = {
  desktop: Monitor,
  mobile: Smartphone,
  tablet: Tablet,
  bot: Bot,
  unknown: CircleHelp,
};

const hasRequest = computed(() => !!props.context && Object.values(props.context).some(Boolean));

const deviceIcon = computed(() => DEVICE_ICONS[props.device?.device_type ?? 'unknown'] ?? CircleHelp);

/** "Windows 10/11", "iOS 17.5", "macOS" — the version only when the agent has a real one. */
const platformName = computed(() =>
  [props.device?.platform_label, props.device?.platform_version].filter(Boolean).join(' '),
);

/** "Chrome 141 · Desktop · Microsoft" — whatever of it is known. */
const deviceMeta = computed(() => {
  const device = props.device;
  if (!device) return '';

  const browser = [device.browser, device.browser_version].filter(Boolean).join(' ');

  return [browser, device.device_label, device.vendor].filter(Boolean).join(' · ');
});

/** The request facts, as data so a new one is an entry rather than more markup. */
const requestRows = computed(() =>
  [
    { key: 'ip', label: 'IP address', value: props.context?.ip, copyable: true },
    { key: 'route', label: 'Route', value: props.context?.route, method: props.context?.method },
  ].filter((row) => row.value),
);

const { copy, copied, isSupported: canCopy } = useClipboard({ copiedDuring: 1500 });

/** Which value was just copied, so only its own button flips to a tick. */
const copiedKey = ref<string | null>(null);

const copyValue = (key: string, value: string) => {
  copiedKey.value = key;
  copy(value);
};

watch(copied, (isCopied) => {
  if (!isCopied) copiedKey.value = null;
});
</script>

<template>
  <div class="flex flex-col gap-2">
    <h4 class="text-sm font-semibold">Device &amp; request</h4>

    <div v-if="hasRequest" class="overflow-hidden rounded-lg border bg-card">
      <!-- The device, as the agent describes it -->
      <div v-if="device" class="flex items-center gap-3 p-3">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
          <component :is="deviceIcon" class="size-5" aria-hidden="true" />
        </div>
        <div class="min-w-0">
          <p class="text-sm font-medium">{{ platformName }}</p>
          <p v-if="deviceMeta" class="text-xs text-muted-foreground">{{ deviceMeta }}</p>
        </div>
      </div>

      <Separator v-if="device && requestRows.length" />

      <dl v-if="requestRows.length" class="grid grid-cols-1 gap-x-6 gap-y-3 p-3 sm:grid-cols-2">
        <div v-for="row in requestRows" :key="row.key" class="flex min-w-0 flex-col gap-0.5">
          <dt class="text-xs text-muted-foreground">{{ row.label }}</dt>
          <dd class="flex min-w-0 items-center gap-1.5 text-sm">
            <Badge v-if="row.method" variant="outline" class="shrink-0 font-mono text-[10px]">{{ row.method }}</Badge>
            <span class="truncate font-mono" :title="row.value">{{ row.value }}</span>
            <button
              v-if="row.copyable && canCopy"
              type="button"
              class="shrink-0 cursor-pointer rounded-sm p-0.5 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
              :aria-label="`Copy ${row.label}`"
              @click="copyValue(row.key, row.value!)">
              <component :is="copiedKey === row.key ? Check : Copy" class="size-3.5" aria-hidden="true" />
            </button>
          </dd>
        </div>
      </dl>

      <!-- The raw agent, in full: the evidence behind the device line above -->
      <template v-if="context?.user_agent">
        <Separator />
        <div class="flex flex-col gap-1.5 p-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-muted-foreground">User agent</span>
            <button
              v-if="canCopy"
              type="button"
              class="inline-flex cursor-pointer items-center gap-1 rounded-sm text-xs text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
              @click="copyValue('user_agent', context.user_agent)">
              <component :is="copiedKey === 'user_agent' ? Check : Copy" class="size-3.5" aria-hidden="true" />
              {{ copiedKey === 'user_agent' ? 'Copied' : 'Copy' }}
            </button>
          </div>
          <code class="block rounded-md bg-muted px-2.5 py-2 font-mono text-xs leading-relaxed break-all whitespace-pre-wrap">{{ context.user_agent }}</code>
        </div>
      </template>
    </div>

    <div
      v-else
      class="flex items-start gap-2 rounded-lg border px-3 py-2 text-xs text-muted-foreground">
      <Info class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <span>No request was recorded — this change came from a console command or a queued job.</span>
    </div>
  </div>
</template>
