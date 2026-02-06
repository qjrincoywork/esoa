<script setup lang="ts">
import { ref, onMounted, computed, defineExpose, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSoas } from '@/composables/soas';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type Soa = {
  id?: number
  soanum?: string
  macode?: string
  refid?: string
  upcode?: string
  billcode?: string
  billtype?: string
  billdate?: string
  upload_date?: string
  due_date?: string
  period_coverage?: string
  paid_date?: string
  amount_due?: number
  company_branch?: string
  file_pdf?: string
  file_xls?: string
  status?: string
}
type AccountType = { value: string | number; name: string }
type Account = { value: string | number; name: string }

const { getAccountsByType } = useSoas();
const props = defineProps({
  soa: {
    type: Object as unknown as () => Soa,
    default: () => [],
  },
  account_types: {
    type: Array as unknown as () => AccountType[],
    required: true,
    default: () => [],
  },
  accounts: {
    type: Array as unknown as () => Account[],
    required: true,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const soa = computed<Soa>(() => props.soa as Soa)
const account_types = computed<AccountType[]>(() => props.account_types as AccountType[]);

// Expose a form ref so parent components can access without document.getElementById
const savingForm = ref<HTMLFormElement | null>(null)
const isReadOnly = ref(soa.value?.id ? true : false)
// Selected account type value (bound to Select) — use string|null to match server values
const selectedAccountType = ref<string | null>(null)
let accounts = ref<Account[]>(null)
// const accounts = computed<Account[]>(() => props.accounts as Account[]);

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
  if (!savingForm.value) return null
  return new FormData(savingForm.value)
}
// async function getAccountsByType(type: string | number): Promise<Account[] | null> {
//   if (!type) return null;
//   console.log('selectedAccountType', type);
//   try {
//     const response = await get(`/${slug.value}/get_accounts`,
//       {
//         params: {
//           type: type,
//         }
//       }
//     );

//     if (!response.ok) {
//       //To be Updated the showing of validation errors in the form
//     } else {
//     }
//   } catch (err) {
//     console.error('Error fetching accounts by type:', err);
//   }
//   // return accounts.value.filter(account => account.value === type);
// }

// defineExpose({
//   savingForm,
//   getFormData,
// })
// Initialize selectedAccountType when available/when prop changes
watch(selectedAccountType, async (next) => {
  accounts.value = [];
  if (selectedAccountType.value != null) {
    const result = await getAccountsByType(selectedAccountType.value);
    console.log('watch accounts', result);
    accounts.value = result;
  }
}, { immediate: true })

onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: savingForm.value })
  }
})
</script>

<template>
  <form ref="savingForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="md:col-span-2 hidden">
      <Input
        type="hidden"
        class="mt-1 block w-full"
        name="id"
        :default-value="soa?.id"
      />
      <Input
        type="hidden"
        class="mt-1 block w-full"
        name="soanum"
        :default-value="soa?.soanum"
      />
    </div>

    <div v-if="soa?.id ? false : true">
      <div class="grid gap-2 md:col-span-1">
        <Label for="account_type">Account Type</Label>
        <Select
          id="account_type"
          class="mt-1 block w-full"
          name="account_type"
          v-model="selectedAccountType"
          :default-value="undefined"
        >
          <!-- :default-value="detail?.gender_id ? String(detail?.gender_id) : undefined" -->
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select an account type" />
          </SelectTrigger>
          <SelectContent class="w-full">
            <SelectGroup>
              <SelectLabel>Account Type</SelectLabel>
              <SelectItem
                v-for="account_type in account_types"
                :key="account_type.value"
                :value="String(account_type.value)"
              >
              {{ account_type.name }}
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="acount">Account</Label>
        <Select
          id="acount"
          class="mt-1 block w-full"
          name="acount"
          :default-value="undefined"
        >
          <!-- :default-value="detail?.gender_id ? String(detail?.gender_id) : undefined" -->
          <SelectTrigger class="w-full">
            <SelectValue placeholder="Select an Account" />
          </SelectTrigger>
          <SelectContent class="w-full">
            <SelectGroup>
              <SelectLabel>Account</SelectLabel>
              <SelectItem
                v-for="account in accounts"
                :key="account.value"
                :value="String(account.value)"
              >
              {{ account.name }}
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    </div>

    <div v-if="soa?.id ? true : false">

      <div class="grid gap-2 md:col-span-1">
        <Label for="company_branch">Company / Branch</Label>
        <Input
          id="company_branch"
          class="mt-1 block w-full"
          name="company_branch"
          :default-value="soa?.company_branch"
          autocomplete="company_branch"
          placeholder="Company / Branch"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="billtype">Bill Type</Label>
        <Input
          id="billtype"
          class="mt-1 block w-full"
          name="billtype"
          :default-value="soa?.billtype"
          autocomplete="billtype"
          placeholder="Bill Type"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="billdate">Bill Date</Label>
        <Input
          id="billdate"
          class="mt-1 block w-full"
          name="billdate"
          :default-value="soa?.billdate"
          autocomplete="billdate"
          placeholder="Bill Date"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="upload_date">SOA Upload Date</Label>
        <Input
          id="upload_date"
          class="mt-1 block w-full"
          name="upload_date"
          :default-value="soa?.upload_date"
          autocomplete="upload_date"
          placeholder="SOA Upload Date"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="period_coverage">Covered Period</Label>
        <Input
          id="period_coverage"
          class="mt-1 block w-full"
          name="period_coverage"
          :default-value="soa?.period_coverage"
          autocomplete="period_coverage"
          placeholder="Covered Period"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="due_date">Due Date</Label>
        <Input
          id="due_date"
          class="mt-1 block w-full"
          name="due_date"
          :default-value="soa?.due_date"
          autocomplete="due_date"
          placeholder="Due Date"
          :readonly="isReadOnly"
        />
      </div>

      <div class="grid gap-2 md:col-span-1">
        <Label for="amount_due">Amount Due</Label>
        <Input
          id="amount_due"
          class="mt-1 block w-full"
          name="amount_due"
          :default-value="soa?.amount_due"
          autocomplete="amount_due"
          placeholder="Amount Due"
        />
      </div>
    </div>

  </form>
</template>
