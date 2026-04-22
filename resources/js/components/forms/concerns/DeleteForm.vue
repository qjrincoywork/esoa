<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';

type Concern = {
  id?: number
  title?: string
  deleted_at?: string
}

const props = defineProps({
  concern: {
    type: Object as unknown as () => Concern,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const concern = computed<Concern>(() => props.concern as Concern)
const concernDeleteForm = ref<HTMLFormElement | null>(null)
const message = concern.value.deleted_at ? 'Restore' : 'Delete'

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!concernDeleteForm.value) return null
    return new FormData(concernDeleteForm.value)
}

defineExpose({
    concernDeleteForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: concernDeleteForm.value })
    }
})
</script>

<template>
  <form ref="concernDeleteForm">
    <div class="hidden">
      <input type="hidden" name="_method" value="DELETE" />
      <input type="hidden" name="id" :value="concern.id" />
    </div>
    <p>Are you sure you want to {{ message.toLowerCase() }} this concern?</p>
    <p><strong>{{ concern.title }}</strong></p>
  </form>
</template>
