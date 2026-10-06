<script setup lang="ts">
import { ref, computed } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { ChevronRight } from 'lucide-vue-next'
import type { PermissionOption } from '@/composables/users'

/**
 * Searchable, module-grouped permission picker.
 *
 * Selection lives in state rather than in the checkboxes, and is submitted through
 * hidden inputs rendered from it, so permissions hidden by the search are still
 * posted — filtering changes what is shown, never what is saved.
 */
const props = withDefaults(defineProps<{
  permissions: PermissionOption[]
  /** Form field name each selected id is posted under. */
  name?: string
  /** Permission id → names of the roles that already grant it (single-user only). */
  inherited?: Record<string, string[]>
}>(), {
  name: 'permissions[]',
  inherited: () => ({}),
})

const selected = defineModel<number[]>({ default: () => [] })

const search = ref('')
const selectedSet = computed(() => new Set(selected.value))

type PermissionGroup = { key: string; label: string; items: PermissionOption[] }

const groups = computed<PermissionGroup[]>(() => {
  const term = search.value.toLowerCase().trim()
  const byKey = new Map<string, PermissionGroup>()

  for (const permission of props.permissions) {
    if (term && ![permission.name, permission.label, permission.group_label].some((v) => v.toLowerCase().includes(term))) {
      continue
    }

    let group = byKey.get(permission.group)
    if (!group) {
      group = { key: permission.group, label: permission.group_label, items: [] }
      byKey.set(permission.group, group)
    }
    group.items.push(permission)
  }

  return [...byKey.values()].sort((a, b) => a.label.localeCompare(b.label))
})

const visibleIds = computed(() => groups.value.flatMap((g) => g.items.map((p) => p.id)))

function setSelected(ids: number[], checked: boolean) {
  const next = new Set(selected.value)
  ids.forEach((id) => (checked ? next.add(id) : next.delete(id)))
  selected.value = [...next]
}

function groupState(group: PermissionGroup): boolean | 'indeterminate' {
  const count = group.items.filter((p) => selectedSet.value.has(p.id)).length
  if (count === 0) return false
  return count === group.items.length ? true : 'indeterminate'
}

function selectedInGroup(group: PermissionGroup): number {
  return group.items.filter((p) => selectedSet.value.has(p.id)).length
}

function inheritedFrom(id: number): string[] {
  return props.inherited[String(id)] ?? []
}

// Groups start expanded; a search always shows its matches, whatever was collapsed.
const collapsed = ref(new Set<string>())

function isCollapsed(key: string): boolean {
  return !search.value.trim() && collapsed.value.has(key)
}

function toggleCollapsed(key: string) {
  const next = new Set(collapsed.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  collapsed.value = next
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <input v-for="id in selected" :key="id" type="hidden" :name="name" :value="id" />

    <div class="flex flex-col sm:flex-row sm:items-end gap-3">
      <div class="flex-1">
        <Label for="permission-search">Search permissions</Label>
        <Input
          id="permission-search"
          v-model="search"
          class="mt-1 w-full"
          placeholder="Search by module or permission..."
        />
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="text-xs px-2 py-1 border rounded" @click="setSelected(visibleIds, true)">
          Select visible
        </button>
        <button type="button" class="text-xs px-2 py-1 border rounded" @click="setSelected(visibleIds, false)">
          Clear visible
        </button>
      </div>
    </div>

    <div class="text-xs text-muted-foreground">
      {{ selected.length }} of {{ permissions.length }} permission{{ permissions.length !== 1 ? 's' : '' }} selected
    </div>

    <div class="border rounded max-h-96 overflow-auto divide-y">
      <section v-for="group in groups" :key="group.key">
        <div class="flex items-center gap-3 px-3 py-2 bg-muted sticky top-0 z-10">
          <Checkbox
            :model-value="groupState(group)"
            :aria-label="`Toggle all ${group.label} permissions`"
            @update:model-value="(v) => setSelected(group.items.map((p) => p.id), v === true)"
          />
          <button
            type="button"
            class="flex flex-1 items-center gap-2 text-left text-sm font-medium"
            :aria-expanded="!isCollapsed(group.key)"
            @click="toggleCollapsed(group.key)"
          >
            <ChevronRight class="w-4 h-4 transition-transform" :class="{ 'rotate-90': !isCollapsed(group.key) }" />
            <span class="flex-1">{{ group.label }}</span>
            <span class="text-xs font-normal text-muted-foreground">{{ selectedInGroup(group) }}/{{ group.items.length }}</span>
          </button>
        </div>

        <template v-if="!isCollapsed(group.key)">
          <label
            v-for="permission in group.items"
            :key="permission.id"
            class="flex items-center gap-3 px-3 py-2 pl-9 text-sm border-t cursor-pointer hover:bg-muted/50"
          >
            <Checkbox
              :model-value="selectedSet.has(permission.id)"
              @update:model-value="(v) => setSelected([permission.id], v === true)"
            />
            <span class="flex-1 min-w-0">
              <span class="block">{{ permission.label }}</span>
              <span class="block text-xs text-muted-foreground truncate">{{ permission.name }}</span>
            </span>
            <span
              v-if="inheritedFrom(permission.id).length"
              class="shrink-0 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
              :title="`Already granted by role: ${inheritedFrom(permission.id).join(', ')}`"
            >
              via {{ inheritedFrom(permission.id).join(', ') }}
            </span>
          </label>
        </template>
      </section>

      <div v-if="!groups.length" class="px-3 py-4 text-center text-sm text-muted-foreground">
        No permissions found.
      </div>
    </div>
  </div>
</template>
