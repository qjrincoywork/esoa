<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Permission = {
  id?: number
  name?: string | number
  guard_name?: string | number
}

const props = defineProps({
  permission: {
    type: Object as unknown as () => Permission,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// fallback to permission for backward compatibility
const permission = computed<Permission>(() => props.permission as Permission)

// Expose a form ref so parent components can access without document.getElementById
const permissionEditForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!permissionEditForm.value) return null
    return new FormData(permissionEditForm.value)
}

defineExpose({
    permissionEditForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: permissionEditForm.value })
    }
})
</script>

<template>
  <form ref="permissionEditForm" class="grid grid-cols-1 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="permission?.id"
        />
    </div>
    <div class="grid gap-2 md:col-span-1">
        <Label for="name">Name</Label>
        <Input
            id="name"
            class="mt-1 block w-full"
            name="name"
            :default-value="permission?.name"
            autocomplete="name"
            placeholder="Name"
        />
    </div>
    <div class="grid gap-2 md:col-span-1">
        <Label for="guard_name">Guard Name</Label>
        <Input
            id="guard_name"
            class="mt-1 block w-full"
            name="guard_name"
            :default-value="permission?.guard_name"
            autocomplete="guard_name"
            placeholder="Guard Name"
        />
    </div>
  </form>
</template>
