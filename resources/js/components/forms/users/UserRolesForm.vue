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

const props = defineProps<{
  user: { id: number | string; username?: string }
  all_roles: Role[]
  user_roles: Role[]
  onReady?: (api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void
}>()

const formRef = ref<HTMLFormElement | null>(null)
const search = ref('')

const selectedRoleIds = ref<string[]>(
  props.user_roles?.map((r) => String(r.id)) ?? [],
)

const filteredRoles = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) return props.all_roles
  return props.all_roles.filter((r) =>
    String(r.name ?? '').toLowerCase().includes(term),
  )
})

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
  // Ensure selected IDs match server-provided user_roles
  if (Array.isArray(props.user_roles)) {
    selectedRoleIds.value = props.user_roles
      .map((r) => (r.id != null ? String(r.id) : null))
      .filter((id): id is string => id !== null)
  }

  props.onReady?.({ getFormData, formRef: formRef.value })
})

defineExpose({ formRef, getFormData })
</script>

<template>
  <form ref="formRef" class="flex flex-col gap-4">
    <input type="hidden" name="user_id" :value="user.id" />

    <div>
      <Label>User</Label>
      <div class="mt-1 font-semibold">
        {{ user.username || user.id }}
      </div>
    </div>

    <div class="flex items-center gap-3">
      <div class="flex-1">
        <Label for="role-search">Search roles</Label>
        <Input
          id="role-search"
          v-model="search"
          class="mt-1 w-full"
          placeholder="Search by role name..."
        />
      </div>
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

