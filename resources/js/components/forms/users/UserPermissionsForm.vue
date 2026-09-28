<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Label } from '@/components/ui/label'
import PermissionChecklist from '@/components/forms/users/PermissionChecklist.vue'
import type { PermissionOption, UserPermissionState } from '@/composables/users'

const props = defineProps<{
  user: UserPermissionState
  all_permissions: PermissionOption[]
  onReady?: (api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void
}>()

const formRef = ref<HTMLFormElement | null>(null)
const selectedIds = ref<number[]>([...props.user.direct_permission_ids])

function getFormData(): FormData | null {
  if (!formRef.value) return null
  return new FormData(formRef.value)
}

onMounted(() => {
  props.onReady?.({ getFormData, formRef: formRef.value })
})

defineExpose({ formRef, getFormData })
</script>

<template>
  <form ref="formRef" class="flex flex-col gap-4">
    <input type="hidden" name="user_id" :value="user.id" />

    <div class="flex flex-wrap gap-6">
      <div>
        <Label>User</Label>
        <div class="mt-1 font-semibold">
          {{ user.username || user.email || user.id }}
        </div>
      </div>
      <div>
        <Label>Roles</Label>
        <div class="mt-1 flex flex-wrap gap-1">
          <span
            v-for="role in user.roles"
            :key="role"
            class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
          >
            {{ role }}
          </span>
          <span v-if="!user.roles.length" class="text-sm text-muted-foreground">None</span>
        </div>
      </div>
    </div>

    <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
      These permissions are granted <strong>directly</strong> to this user, on top of their roles.
      A permission marked <em>via</em> a role stays in effect even if its direct grant is removed.
    </div>

    <PermissionChecklist
      v-model="selectedIds"
      :permissions="all_permissions"
      :inherited="user.inherited_permissions"
    />
  </form>
</template>
