<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';

type UserBasic = {
  id?: number
  username?: string | number
  email?: string | number
}

const props = defineProps({
  user: {
    type: Object as unknown as () => UserBasic,
    required: true,
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// Prefer nested user_detail if present; fallback to user for backward compatibility
const user = computed<UserBasic>(() => props.user as UserBasic)
const userDeleteForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!userDeleteForm.value) return null
    return new FormData(userDeleteForm.value)
}

defineExpose({
    userDeleteForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: userDeleteForm.value })
    }
})
</script>

<template>
  <form ref="userDeleteForm">
    <div class="hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="user.id"
        />
    </div>
  </form>
  Are you sure you want to delete this?
</template>
