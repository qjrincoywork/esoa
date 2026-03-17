<script setup lang="ts">
import { ref, computed, onMounted, defineExpose } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { Role } from '@/composables/roles'
import type { Permission } from '@/composables/permissions'
import Switch from '@/components/ui/switch/Switch.vue'

const props = defineProps<{
  role: Role & { permissions?: { id: number | string }[] }
  allPermissions: Permission[]
  access_permissions: Permission[]
  onReady?: (api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void
}>()

const formRef = ref<HTMLFormElement | null>(null)
const search = ref('')

const selectedIds = ref<string[]>([])

const accessPermissions = computed<Permission[]>(() => props.access_permissions)
const filteredPermissions = computed(() => {
  const term = search.value.toLowerCase().trim()
  if (!term) return props.allPermissions
  return props.allPermissions.filter(p =>
    (p.name ?? '').toString().toLowerCase().includes(term)
  )
})

function isChecked(id: number | string) {
  return accessPermissions.value.some(p => p.id == id)
}

function togglePermission(id: number | string, checked: boolean) {
  const key = String(id)
  if (checked) {
    if (!selectedIds.value.includes(key)) {
      selectedIds.value = [...selectedIds.value, key]
    }
  } else {
    selectedIds.value = selectedIds.value.filter(existing => existing !== key)
  }
}

function selectAllVisible() {
  const current = new Set(selectedIds.value)
  filteredPermissions.value.forEach(p => {
    if (p.id != null) current.add(String(p.id))
  })
  selectedIds.value = Array.from(current)
}

function clearAllVisible() {
  const current = new Set(selectedIds.value)
  filteredPermissions.value.forEach(p => {
    if (p.id != null) current.delete(String(p.id))
  })
  selectedIds.value = Array.from(current)
}

function getFormData(): FormData | null {
  if (!formRef.value) return null
  return new FormData(formRef.value)
}

defineExpose({ formRef, getFormData })

onMounted(() => {
  if (Array.isArray(props.role.permissions)) {
    selectedIds.value = props.role.permissions
      .map((p) => (p.id != null ? String(p.id) : null))
      .filter((id): id is string => id !== null)
  }
  props.onReady?.({ getFormData, formRef: formRef.value })
})
</script>

<template>
  <form ref="formRef" class="flex flex-col gap-4">
    <Input type="hidden" name="role_id" :default-value="role.id" />

    <div>
      <Label>Role</Label>
      <div class="mt-1 font-semibold">
        {{ role.name }}
      </div>
    </div>

    <div class="flex items-center justify-between gap-3">
      <div class="flex-1">
        <Label for="permission-search">Search permissions</Label>
        <Input
          id="permission-search"
          v-model="search"
          class="mt-1 w-full"
          placeholder="Search by permission name..."
        />
      </div>
      <div class="flex items-end gap-2 mt-6">
        <button
          type="button"
          class="text-xs px-2 py-1 border rounded"
          @click="selectAllVisible"
        >
          Select visible
        </button>
        <button
          type="button"
          class="text-xs px-2 py-1 border rounded"
          @click="clearAllVisible"
        >
          Clear visible
        </button>
      </div>
    </div>

    <div class="border rounded max-h-72 overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-muted sticky top-0 z-10">
          <tr>
            <th class="px-3 py-2 text-left w-10">#</th>
            <th class="px-3 py-2 text-left">Permission</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="permission in filteredPermissions"
            :key="permission.id"
            class="border-t"
          >
            <td class="px-3 py-2">
              <Switch
                name="permissions[]"
                :default-value="isChecked(permission.id)"
                :value="permission.name"
              />
            </td>
            <td class="px-3 py-2">
              {{ permission.name }}
            </td>
          </tr>
          <tr v-if="!filteredPermissions.length">
            <td colspan="2" class="px-3 py-4 text-center text-muted-foreground">
              No permissions found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </form>
</template>

