<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';

type UserBasic = {
  id?: number
  username?: string | number
  email?: string | number
}

type Concern = {
  id?: number
  user_id?: number
  billing_invoice?: string
  type?: string
  title?: string
  description?: string
  status?: string
  attachment?: string
}

// const emit = defineEmits<{
//   submit: [formData: FormData]
// }>();

const props = defineProps({
  auth: {
    type: Object as unknown as () => UserBasic,
    default: () => ({}),
  },
  concern: {
    type: Object as unknown as () => Concern,
    default: () => ({}),
  },
  concern_types: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  ticket_statuses: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null }) => void) | undefined,
    required: false,
  },
});

const form = ref({
  user_id: props.concern.user_id || '',
  billing_invoice: props.concern.billing_invoice || '',
  type: props.concern.type || '',
  title: props.concern.title || '',
  description: props.concern.description || '',
  status: props.concern.status || '',
  attachment: null as File | null,
});

const types = computed(() => props.concern_types || []); // Assuming concern_types is passed as a prop
const statuses = computed(() => props.ticket_statuses || []); // Assuming ticket_statuses is passed as a prop

const concernForm = ref<HTMLFormElement | null>(null);

function getFormData(): FormData | null {
  if (!concernForm.value) return null;
  return new FormData(concernForm.value);
}

defineExpose({
  getFormData,
});

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData });
  }
});
</script>

<template>
  <form ref="concernForm" class="grid grid-cols-1 md:grid-cols-1 gap-3" enctype="multipart/form-data">
    <input v-if="concern.id" type="hidden" name="id" :value="concern.id ?? ''" />
    <div class="grid gap-2 md:col-span-1">
      <Label for="billing_invoice">Billing Invoice<span class="text-red-400">*</span></Label>
      <Input
        class="mt-1 block w-full"
        id="billing_invoice"
        v-model="form.billing_invoice"
      />
    </div>
    <div class="grid gap-2 md:col-span-1">
      <Label for="type">Type<span class="text-red-400">*</span></Label>
      <Select
        class="mt-1 block w-full"
        v-model="form.type"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select type" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectItem
            v-for="type in types"
            :key="type.value"
            :value="String(type.value)"
          >
            {{ type.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="grid gap-2 md:col-span-1">
      <Label for="title">Title<span class="text-red-400">*</span></Label>
      <Input
        class="mt-1 block w-full"
        id="title"
        v-model="form.title"
      />
    </div>
    <div class="grid gap-2 md:col-span-1">
      <Label for="description">Description<span class="text-red-400">*</span></Label>
      <Textarea
        placeholder="Type the description here."
        class="mt-1 block w-full"
        id="description"
        v-model="form.description"
      />
    </div>
    <div class="grid gap-2 md:col-span-1" v-if="auth.is_superadmin">
      <Label for="status">Status<span class="text-red-400">*</span></Label>
      <Select
        class="mt-1 block w-full"
        v-model="form.status"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select status" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectItem
            v-for="status in statuses"
            :key="status.value"
            :value="String(status.value)"
          >
            {{ status.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="grid gap-2 md:col-span-1">
      <Label for="attachment">Attachment</Label>
      <Input
        class="mt-1 block w-full"
        id="attachment"
        name="attachment"
        type="file"
        @change="(e) => form.attachment = (e.target as HTMLInputElement).files?.[0] || null"
      />
    </div>
  </form>
</template>
