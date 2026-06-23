<script setup lang="ts">
import { onMounted } from 'vue';

interface SoaItem {
  id?: number | string;
  soanum?: string;
  soa_number?: string;
  deleted_at?: string | null;
  [key: string]: any;
}

const props = defineProps<{
  soa: SoaItem;
  isDeleted: boolean;
  onReady: (api: { getFormData: () => FormData }) => void;
}>();

const getFormData = (): FormData => {
  const formData = new FormData();
  formData.append('id', String(props.soa.id));
  return formData;
};

onMounted(() => props.onReady({ getFormData }));
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center gap-1.5">
      <span class="text-sm font-medium text-[var(--color-text)]">
        {{ soa.soanum ?? soa.soa_number ?? `SOA #${soa.id}` }}
      </span>
    </div>

    <div :class="[
      'rounded-md px-3 py-2 text-xs',
      isDeleted
        ? 'bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'
        : 'bg-red-50   dark:bg-red-900/20   text-red-800   dark:text-red-300',
    ]">
      {{ isDeleted
        ? 'This SOA was previously deleted. Restoring will reinstate the record.'
        : 'This will soft-delete the SOA. It will be hidden from the system.' }}
    </div>

    <p class="text-xs text-[var(--color-text-muted)]">
      {{ isDeleted ? 'Are you sure you want to restore this SOA?' : 'Are you sure you want to delete this SOA?' }}
    </p>
  </div>
</template>
