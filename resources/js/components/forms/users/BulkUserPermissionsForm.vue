<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Label } from '@/components/ui/label'
import PermissionChecklist from '@/components/forms/users/PermissionChecklist.vue'
import type { PermissionAssignmentModeOption, PermissionOption, User } from '@/composables/users'

const props = defineProps<{
  users: User[]
  all_permissions: PermissionOption[]
  modes: PermissionAssignmentModeOption[]
  onReady?: (api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void
}>()

const formRef = ref<HTMLFormElement | null>(null)
const selectedIds = ref<number[]>([])
const mode = ref<string>(props.modes[0]?.value ?? 'sync')

const displayedUsers = computed(() => props.users.slice(0, 5))
const hiddenCount = computed(() => Math.max(0, props.users.length - 5))
const activeMode = computed(() => props.modes.find((m) => m.value === mode.value))

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
    <input
      v-for="user in users"
      :key="user.id"
      type="hidden"
      name="user_ids[]"
      :value="user.id"
    />
    <input type="hidden" name="mode" :value="mode" />

    <div>
      <Label>Selected Users</Label>
      <div class="mt-1 flex flex-wrap gap-1">
        <span
          v-for="user in displayedUsers"
          :key="user.id"
          class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium"
        >
          {{ user.username || user.email || user.id }}
        </span>
        <span
          v-if="hiddenCount > 0"
          class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
        >
          +{{ hiddenCount }} more
        </span>
      </div>
    </div>

    <div>
      <Label>Apply as</Label>
      <div class="mt-1 inline-flex rounded-md border p-0.5" role="radiogroup" aria-label="Assignment mode">
        <button
          v-for="option in modes"
          :key="option.value"
          type="button"
          role="radio"
          :aria-checked="mode === option.value"
          class="px-3 py-1 text-xs rounded cursor-pointer transition-colors"
          :class="mode === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
          @click="mode = option.value"
        >
          {{ option.name }}
        </button>
      </div>
    </div>

    <div
      v-if="activeMode"
      class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700 dark:border-orange-800 dark:bg-orange-900/20 dark:text-orange-400"
    >
      {{ activeMode.description }}
      Permissions inherited through roles are not affected.
    </div>

    <PermissionChecklist v-model="selectedIds" :permissions="all_permissions" />
  </form>
</template>
