<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Auth, User, UserDetail } from '@/types';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type Soa = {
  id?: number
  user_id?: number
  soa_number?: string
  account_type?: string | number
  account_code?: string
  branch_code?: string
  billing_ref?: string
  bill_type?: number
  status?: number
  due_date?: string
  period_date_from?: string
  period_date_to?: string
  amount?: number
  file_pdf?: string
  file_xls?: string
}

const props = defineProps({
  soa: {
    type: Object as unknown as () => Soa,
    default: () => ({}),
  },
  status_types: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const soa = computed<Soa>(() => props.soa as Soa)
const status_types = computed<{ value: string | number; name: string }[]>(() => (props.status_types ?? []) as { value: string | number; name: string }[]);
const page = usePage();
const auth = computed(() => (page.props as any).auth as Auth);
const user = computed(() => auth.value?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);

// Expose a form ref so parent components can access without document.getElementById
const savingForm = ref<HTMLFormElement | null>(null)
const selectedStatus = ref<string | number>(soa.value?.status ?? '2')
const isSyncingFromSoa = ref(false)
const isAccountBranchAdmin = !userDetail.value?.has_employee_no;

const filteredStatusTypes = computed(() => {
  return isAccountBranchAdmin ? status_types.value?.filter(s => (s.value == 2 || s.value == 4)) : []
});
// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
  if (!savingForm.value) return null
  return new FormData(savingForm.value)
}

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: savingForm.value })
  }
})


watch(soa, (val: Soa | undefined) => {
  if (!val) return;
  isSyncingFromSoa.value = true
  if (val.status != null) selectedStatus.value = String(val.status);
  isSyncingFromSoa.value = false
}, { immediate: true })

</script>

<template>
  <form ref="savingForm" class="grid grid-cols-1 md:grid-cols-1 gap-3" enctype="multipart/form-data">
    <div class="md:col-span-2 hidden">
      <!-- Use native hidden inputs so FormData always reflects latest reactive values -->
      <input type="hidden" name="id" :value="soa?.id ?? ''" />
      <input type="hidden" name="status" :value="String(selectedStatus ?? '')" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="status">Status<span class="text-red-400">*</span></Label>
      <Select
        id="status"
        class="mt-1 block w-full"
        v-model="selectedStatus"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select status" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Status</SelectLabel>
            <SelectItem
              v-for="st in filteredStatusTypes"
              :key="st.value"
              :value="String(st.value)"
            >
              {{ st.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>
  </form>
</template>
