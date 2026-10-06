<script setup lang="ts">
/**
 * Submodules of one module (navigation_modules.ref_id = parentModule.id), opened in the
 * top pane from a row click on the details pane's Modules tab. Same full-CRUD list, just
 * scoped one level deeper — the fetch/columns plumbing lives in NavigationModuleList.
 */
import NavigationModuleList from '@/components/forms/navigations/NavigationModuleList.vue';

defineProps<{
  navigationId: number;
  parentModule: { id: number; name: string };
}>();
</script>

<template>
  <div class="flex flex-col gap-3">
    <p class="text-xs text-[var(--color-text-muted)]">
      Submodules registered under
      <span class="font-medium text-[var(--color-text)]">{{ parentModule.name }}</span>.
    </p>

    <NavigationModuleList
      :navigation-id="navigationId"
      :ref-id="parentModule.id"
      empty-description="This module has no submodules yet." />
  </div>
</template>
