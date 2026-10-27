<script setup lang="ts">
import { onMounted } from 'vue';
import type { Advisory } from '@/composables/advisories';

const props = defineProps<{
  advisory: Advisory;
  newActiveValue: 0 | 1;
  onReady: (api: { getFormData: () => FormData }) => void;
}>();

const getFormData = (): FormData => {
  const formData = new FormData();
  formData.append('id', String(props.advisory.id));
  formData.append('is_active', String(props.newActiveValue));
  return formData;
};

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
  <div class="space-y-4">
    <p class="text-sm font-medium">{{ advisory.title }}</p>

    <div class="flex items-center gap-2">
      <span :class="[
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        newActiveValue
          ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'
          : 'bg-green-100  text-green-800  dark:bg-green-900/30  dark:text-green-400',
      ]">
        {{ newActiveValue ? 'Disabled' : 'Enabled' }}
      </span>

      <svg class="w-4 h-4 shrink-0 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
      </svg>

      <span :class="[
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
        newActiveValue
          ? 'bg-green-100  text-green-800  dark:bg-green-900/30  dark:text-green-400'
          : 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
      ]">
        {{ newActiveValue ? 'Enabled' : 'Disabled' }}
      </span>
    </div>

    <p class="text-xs text-muted-foreground">
      {{ newActiveValue
        ? 'Users will see this advisory after signing in whenever its schedule allows.'
        : 'Users will stop seeing this advisory after signing in. Its schedule is kept.' }}
    </p>
  </div>
</template>
