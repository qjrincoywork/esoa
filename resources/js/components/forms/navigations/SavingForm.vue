<script setup lang="ts">
import { ref, onMounted, computed, defineExpose } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type Status = { value: number; name: string }
type Navigation = {
  id?: number
  name?: string | number
  label?: string
  icon?: string
  created_by?: number
  status?: number
}

const props = defineProps({
  navigation: {
    type: Object as unknown as () => Navigation,
    default: () => [],
  },
  statuses: {
    type: Array as unknown as () => Status[],
    required: true,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const navigation = computed<Navigation>(() => props.navigation as Navigation)
const statuses = computed<Status[]>(() => props.statuses as Status[])
console.log(statuses)

// Expose a form ref so parent components can access without document.getElementById
const navigationEditForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
    if (!navigationEditForm.value) return null
    return new FormData(navigationEditForm.value)
}

defineExpose({
    navigationEditForm,
    getFormData,
})

onMounted(() => {
    if (typeof props.onReady === 'function') {
        props.onReady({ getFormData, formRef: navigationEditForm.value })
    }
})
</script>

<template>
  <form ref="navigationEditForm" class="grid grid-cols-1 md:grid-cols-1 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
            id="id"
            type="hidden"
            class="mt-1 block w-full"
            name="id"
            :default-value="navigation?.id"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="name">Name</Label>
        <Input
            id="name"
            class="mt-1 block w-full"
            name="name"
            :default-value="navigation?.name"
            autocomplete="name"
            placeholder="Name"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="label">Label</Label>
        <Input
            id="label"
            class="mt-1 block w-full"
            name="label"
            :default-value="navigation?.label"
            autocomplete="label"
            placeholder="Label"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="icon">Icon</Label>
        <Input
            id="icon"
            class="mt-1 block w-full"
            name="icon"
            :default-value="navigation?.icon"
            autocomplete="icon"
            placeholder="Icon"
        />
    </div>

    <div class="grid gap-2 md:col-span-1">
        <Label for="status">Status</Label>
        <Select
            id="status"
            class="mt-1 block w-full"
            name="status"
            :default-value="navigation?.status ? Number(navigation?.status) : undefined"
        >
          <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a status" />
          </SelectTrigger>
          <SelectContent class="w-full">
              <SelectGroup>
                  <SelectLabel>Status</SelectLabel>
                  <SelectItem
                          v-for="status in statuses"
                          :key="status.value"
                          :value="Number(status.value)"
                  >
                  {{ status.name }}
                  </SelectItem>
              </SelectGroup>
          </SelectContent>
        </Select>
    </div>
  </form>
</template>
