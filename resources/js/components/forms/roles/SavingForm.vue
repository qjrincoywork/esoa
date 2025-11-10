<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

// Expose a form ref so parent components can access without document.getElementById
const roleEditForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!roleEditForm.value) return null
    return new FormData(roleEditForm.value)
}

defineExpose({
    roleEditForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: roleEditForm.value })
    }
})
</script>

<template>
  <form ref="roleEditForm" class="grid grid-cols-1 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="role.id"
        />
    </div>
    <div class="grid gap-2 md:col-span-1">
        <Label for="name">Name</Label>
        <Input
            id="name"
            class="mt-1 block w-full"
            name="name"
            :default-value="role.name"
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
            :default-value="role.guard_name"
            autocomplete="guard_name"
            placeholder="Guard Name"
        />
    </div>
  </form>
</template>
