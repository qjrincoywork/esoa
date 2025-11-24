<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';

type Role = {
  id?: number
  name?: string | number
  guard_name?: string | number
}

const props = defineProps({
  role: {
    type: Object as unknown as () => Role,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// fallback to role for backward compatibility
const role = computed<Role>(() => props.role as Role)
const roleDeleteForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!roleDeleteForm.value) return null
    return new FormData(roleDeleteForm.value)
}

defineExpose({
    roleDeleteForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: roleDeleteForm.value })
    }
})
</script>

<template>
  <form ref="roleDeleteForm">
    <div class="hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="role.id"
        />
    </div>
  </form>
  Are you sure you want to delete this?
</template>
