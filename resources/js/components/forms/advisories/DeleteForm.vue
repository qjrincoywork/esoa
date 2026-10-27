<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import type { Advisory } from '@/composables/advisories';

const props = defineProps({
  advisory: {
    type: Object as unknown as () => Advisory,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const advisory = computed<Advisory>(() => props.advisory as Advisory);
const advisoryDeleteForm = ref<HTMLFormElement | null>(null);
const message = advisory.value.deleted_at ? 'Restore' : 'Delete';

function getFormData(): FormData | null {
  if (!advisoryDeleteForm.value) return null;
  return new FormData(advisoryDeleteForm.value);
}

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: advisoryDeleteForm.value });
  }
});
</script>

<template>
  <form ref="advisoryDeleteForm">
    <div class="hidden">
      <input type="hidden" name="id" :value="advisory.id" />
    </div>
    <p>Are you sure you want to {{ message.toLowerCase() }} this advisory?</p>
    <p><strong>{{ advisory.title }}</strong></p>
  </form>
</template>
