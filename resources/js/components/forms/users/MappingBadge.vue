<script setup lang="ts">
/**
 * One account/branch mapping badge — kind, expired or no members.
 *
 * Label and colors come from `App\Enums\AccountMappingBadge` and are rendered as sent;
 * only the layout lives here, so both mapping panels draw every badge alike.
 */
import type { MappingBadge } from '@/composables/users';

defineProps<{
  badge: MappingBadge;
}>();
</script>

<template>
  <span
    class="shrink-0 rounded px-1 py-px text-[10px] font-semibold whitespace-nowrap uppercase"
    :class="badge.color">
    {{ badge.label }}
  </span>
</template>
