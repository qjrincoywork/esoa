<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';

type Permission = {
  id?: number
  name?: string | number
  guard_name?: string | number
}

const props = defineProps({
  permission: {
    type: Object as unknown as () => Permission,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// fallback to permission for backward compatibility
const permission = computed<Permission>(() => props.permission as Permission)
const permissionDeleteForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!permissionDeleteForm.value) return null
    return new FormData(permissionDeleteForm.value)
}

defineExpose({
    permissionDeleteForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: permissionDeleteForm.value })
    }
})
</script>

<template>
  <form ref="permissionDeleteForm">
    <div class="hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="permission.id"
        />
    </div>
  </form>
  Are you sure you want to delete this?
</template>
