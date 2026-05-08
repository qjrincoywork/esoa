<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectTrigger, SelectContent, SelectItem, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Auth, User, UserDetail } from '@/types';

type Concern = {
  id?: number
  user_id?: number
  billing_invoice?: string
  type?: string
  title?: string
  description?: string
  status?: string
  attachment?: string
  attachment_preview_token?: string
}

// const emit = defineEmits<{
//   submit: [formData: FormData]
// }>();

const props = defineProps({
  auth: {
    type: Object as unknown as () => Auth,
    default: () => ({}),
  },
  user: {
    type: Object as unknown as () => User,
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
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});
const concern = computed<Concern>(() => props.concern as Concern)

const form = ref({
  id: concern.value?.id || '',
  billing_invoice: concern.value?.billing_invoice || '',
  type: concern.value?.type || '',
  title: concern.value?.title || '',
  description: concern.value?.description || '',
  status: concern.value?.status || '',
  attachment: null as File | null,
});

const user = computed(() => props.auth?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);
const selectedStatus = ref<string | number>(concern.value.status != null ? String(concern.value.status) : '1')
const selectedType = ref<string | number>(concern.value.type != null ? String(concern.value.type) : '1')
const types = computed(() => props.concern_types || []); // Assuming concern_types is passed as a prop
const statuses = computed(() => props.ticket_statuses || []); // Assuming ticket_statuses is passed as a prop
const concernForm = ref<HTMLFormElement | null>(null);

function getFormData(): FormData | null {
  if (!concernForm.value) return null;
  return new FormData(concernForm.value);
}

// defineExpose({
//   getFormData,
// });

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: concernForm.value });
  }
});
const openTab = () => {
  window.open(`/concerns/preview_file?token=${encodeURIComponent(concern.value?.attachment_preview_token)}`, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <form ref="concernForm" class="grid grid-cols-1 md:grid-cols-1 gap-3" enctype="multipart/form-data">
    <div v-if="auth?.is_superadmin || concern?.id == null">
      <div class="md:col-span-2 hidden">
        <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
        <input v-if="concern?.id" type="hidden" name="id" :value="concern?.id" />
        <input type="hidden" name="type" :value="selectedType" />
        <input type="hidden" name="status" :value="selectedStatus" />
      </div>
      <div class="grid gap-2 md:col-span-1">
        <Label for="billing_invoice">Billing Invoice<span class="text-red-400">*</span></Label>
        <Input
          class="mt-1 block w-full"
          id="billing_invoice"
          name="billing_invoice"
          v-model="form.billing_invoice"
        />
      </div>
      <div class="grid gap-2 md:col-span-1">
        <Label for="type">Type<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedType"
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
          name="title"
          v-model="form.title"
        />
      </div>
      <div class="grid gap-2 md:col-span-1">
        <Label for="description">Description<span class="text-red-400">*</span></Label>
        <Textarea
          placeholder="Type the description here."
          class="mt-1 block w-full"
          id="description"
          name="description"
          v-model="form.description"
        />
      </div>
      <div v-if="auth?.is_superadmin" class="grid gap-2 md:col-span-1">
        <Label for="status">Status<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedStatus"
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
        <p
          v-if="concern.attachment"
          class="mt-1 text-xs text-[var(--color-text-muted)]"
        >
          Current:
          <a
            :onClick="openTab"
            target="_blank"
            rel="noopener noreferrer"
            class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
          >
            {{ concern.attachment.split('/').pop() }}
          </a>
        </p>
        <Input
          class="mt-1 block w-full"
          id="attachment"
          name="attachment"
          type="file"
        />
      </div>
    </div>
    <div v-else>
      <div class="md:col-span-2 hidden">
        <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
        <input v-if="concern?.id" type="hidden" name="id" :value="concern?.id" />
        <input type="hidden" name="type" :value="selectedType" />
        <input type="hidden" name="status" :value="selectedStatus" />
      </div>
      <div class="grid gap-2 md:col-span-1">
        <Label for="status">Status<span class="text-red-400">*</span></Label>
        <Select
          class="mt-1 block w-full"
          v-model="selectedStatus"
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
    </div>
  </form>
</template>
