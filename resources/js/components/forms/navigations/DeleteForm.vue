<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';

type Navigation = {
  id?: number
  name?: string | number
  label?: string
  icon?: string
  created_by?: number
  status?: number
}

const props = defineProps({
  navigation: {
    type: Object as unknown as () => Navigation,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const navigation = computed<Navigation>(() => props.navigation as Navigation)
const navigationDeleteForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!navigationDeleteForm.value) return null
    return new FormData(navigationDeleteForm.value)
}

defineExpose({
    navigationDeleteForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: navigationDeleteForm.value })
    }
})
</script>

<template>
  <form ref="navigationDeleteForm">
    <div class="hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="navigation.id"
        />
    </div>
  </form>
  Are you sure you want to delete this?
</template>
