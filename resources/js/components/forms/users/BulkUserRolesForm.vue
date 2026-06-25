<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import Switch from '@/components/ui/switch/Switch.vue'

type Role = {
  id: number | string
  name?: string
  guard_name?: string
}

type User = {
  id: number | string
  username?: string
  email?: string
}

const props = defineProps<{
  users: User[]
  all_roles: Role[]
  onReady?: (api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void
}>()

const formRef = ref<HTMLFormElement | null>(null)
const search = ref('')
const selectedRoleIds = ref<string[]>([])

const filteredRoles = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) return props.all_roles
  return props.all_roles.filter((r) =>
    String(r.name ?? '').toLowerCase().includes(term),
  )
})

const displayedUsers = computed(() => props.users.slice(0, 5))
const hiddenCount = computed(() => Math.max(0, props.users.length - 5))

function isChecked(roleId: number | string | undefined) {
  if (roleId == null) return false
  return selectedRoleIds.value.includes(String(roleId))
}

function toggleRole(roleId: number | string | undefined, checked: boolean) {
  if (roleId == null) return
  const key = String(roleId)
  if (checked) {
    if (!selectedRoleIds.value.includes(key)) {
      selectedRoleIds.value = [...selectedRoleIds.value, key]
    }
  } else {
    selectedRoleIds.value = selectedRoleIds.value.filter((x) => x !== key)
  }
}

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

    <div class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700 dark:border-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
      The selected roles below will <strong>replace</strong> the existing roles for all {{ users.length }} selected user{{ users.length !== 1 ? 's' : '' }}.
    </div>

    <div>
      <Label for="bulk-role-search">Search roles</Label>
      <Input
        id="bulk-role-search"
        v-model="search"
        class="mt-1 w-full"
        placeholder="Search by role name..."
      />
    </div>

    <div class="border rounded max-h-72 overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-muted sticky top-0 z-10">
          <tr>
            <th class="px-3 py-2 text-left w-10">#</th>
            <th class="px-3 py-2 text-left">Role</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="role in filteredRoles"
            :key="role.id"
            class="border-t"
          >
            <td class="px-3 py-2">
              <Switch
                name="roles[]"
                :default-value="isChecked(role.id)"
                :value="role.id"
              />
            </td>
            <td class="px-3 py-2">
              {{ role.name }}
            </td>
          </tr>

          <tr v-if="!filteredRoles.length">
            <td colspan="2" class="px-3 py-4 text-center text-muted-foreground">
              No roles found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </form>
</template>
