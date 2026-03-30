<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { useSoas } from '@/composables/soas';
import { debounce } from '@/composables/utilities/helper';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type UserDetail = {
  is_vc_employee?: number
  account_code?: string
  branch_code?: string
  gender_id?: number
  civil_status_id?: number
  citizenship_id?: number
  department_id?: number
  position_id?: number
  first_name?: string
  middle_name?: string
  last_name?: string
  suffix?: string | number
  birthdate?: string
  employee_no?: string
}
type UserBasic = {
  id?: number
  username?: string | number
  email?: string | number
  user_detail?: UserDetail
}
type Soa = {
  id?: number
  user_id?: number
  soa_number?: string
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
type Account = { value: string | number; name: string }
type AccountType = { value: string | number; name: string }
type Branch = { value: string | number; name: string }
type BillingRef = { value: string | number; name: string }

const { getAccountsByParams, getBranchesByParams, getBillingRefsByParams } = useSoas();
const props = defineProps({
  user: {
    type: Object as unknown as () => UserBasic,
    default: () => ({}),
  },
  soa: {
    type: Object as unknown as () => Soa,
    default: () => ({}),
  },
  account_types: {
    type: Array as unknown as () => AccountType[],
    required: true,
    default: () => [],
  },
  bill_types: {
    type: Array as unknown as () => { value: string | number; name: string }[],
    default: () => [],
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
const billing_refs = ref<BillingRef[]>([])
const account_types = computed<AccountType[]>(() => props.account_types as AccountType[]);
const bill_types = computed<{ value: string | number; name: string }[]>(() => (props.bill_types ?? []) as { value: string | number; name: string }[]);
const status_types = computed<{ value: string | number; name: string }[]>(() => (props.status_types ?? []) as { value: string | number; name: string }[]);
const user = computed<UserBasic>(() => props.user as UserBasic)
const detail = computed<UserDetail>(() => user.value?.user_detail as UserDetail)

// Expose a form ref so parent components can access without document.getElementById
const savingForm = ref<HTMLFormElement | null>(null)
const isReadOnly = ref(soa.value?.id ? true : false)
// Selected account type value (bound to Select) — use string|null to match server values
const selectedAccountType = ref<string | null>(null)
const accounts = ref<Account[]>([])
const branches = ref<Branch[]>([])
const accountPage = ref(1)
const accountLastPage = ref(1)
const branchPage = ref(1)
const branchLastPage = ref(1)
const billingRefPage = ref(1)
const billingRefLastPage = ref(1)
const accountsLoadingMore = ref(false)
const branchesLoadingMore = ref(false)
const billingRefsLoadingMore = ref(false)
const hasMoreAccounts = computed(() => accountPage.value < accountLastPage.value)
const hasMoreBranches = computed(() => branchPage.value < branchLastPage.value)
const hasMoreBillingRefs = computed(() => billingRefPage.value < billingRefLastPage.value)
const isVcEmployee = ref(detail.value?.is_vc_employee != null ? Number(detail.value.is_vc_employee) : 0)
const userId = ref(user?.value?.id ?? undefined)

// Sync from props when user/detail loads (e.g. edit mode with async data)
watch(
  () => detail.value?.is_vc_employee,
  (val) => {
    if (val != null) {
      isVcEmployee.value = Number(val)
    }
  },
  { immediate: true },
)
const accountCode = ref(detail.value?.account_code != null ? String(detail.value.account_code) : (soa.value?.account_code ?? ''))
const branchCode = ref(detail.value?.branch_code != null ? String(detail.value.branch_code) : (soa.value?.branch_code ?? ''))
const billingRef = ref(soa.value?.billing_ref != null ? String(soa.value.billing_ref) : (soa.value?.billing_ref ?? ''))
const searchedAccountName = ref('')
const soaNumber = ref(soa.value?.soa_number ?? '')
const selectedBillType = ref<string | null>(soa.value?.bill_type != null ? String(soa.value.bill_type) : null)
const selectedStatus = ref<string | null>(soa.value?.status != null ? String(soa.value?.status) : 1)
const dueDate = ref(soa.value?.due_date ?? '')
const periodDateFrom = ref(soa.value?.period_date_from ?? '')
const periodDateTo = ref(soa.value?.period_date_to ?? '')
const amount = ref(soa.value?.amount != null ? String(soa.value.amount) : '')
const searchedBranchName = ref('')
const searchedBillingRef = ref('')
const selectedAccount = computed(() =>
  accounts.value?.find(account => String(account.value) === accountCode.value),
)

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

// Fetch accounts by selected type, optional search name, and page (replace or append)
const searchAccountsByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccountType.value) {
    accounts.value = [];
    return;
  }
  if (append) {
    accountsLoadingMore.value = true;
  }
  const result = await getAccountsByParams({
    type: selectedAccountType.value,
    name,
    page,
  });

  if (append) {
    accounts.value = [...(accounts.value ?? []), ...result?.data];
  } else {
    accounts.value = result?.data;
  }
  accountPage.value = result?.current_page;
  accountLastPage.value = result?.last_page;
  accountsLoadingMore.value = false;
}

// Fetch branches by selected type, optional search name, and page (replace or append)
const searchBranchesByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccount.value?.value) {
    branches.value = [];
    return;
  }
  if (append) {
    branchesLoadingMore.value = true;
  }
  const result = await getBranchesByParams({
    account_code: selectedAccount.value?.value,
    name,
    page,
  });
  console.log(result?.data)
  if (append) {
    branches.value = [...(branches.value ?? []), ...result?.data];
  } else {
    branches.value = result?.data;
  }
  branchPage.value = result?.current_page;
  branchLastPage.value = result?.last_page;
  branchesLoadingMore.value = false;
}

// Fetch billing refs by selected account, optional search name, and page (replace or append)
const searchBillingRefsByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccount.value?.value) {
    billing_refs.value = [];
    return;
  }
  if (append) {
    billingRefsLoadingMore.value = true;
  }
  const result = await getBillingRefsByParams({
    account_code: selectedAccount.value?.value,
    name,
    page,
  });

  if (append) {
    billing_refs.value = [...(billing_refs.value ?? []), ...result?.data];
  } else {
    billing_refs.value = result?.data;
  }
  billingRefPage.value = result?.current_page;
  billingRefLastPage.value = result?.last_page;
  billingRefsLoadingMore.value = false;
}

function loadMoreData(input: string) {
  switch (input) {
    case 'accounts':
      if (!hasMoreAccounts.value || accountsLoadingMore.value) return;
      void searchAccountsByParams(searchedAccountName.value, accountPage.value + 1, true);
      break;
    case 'branches':
      if (!hasMoreBranches.value || branchesLoadingMore.value) return;
      void searchBranchesByParams(searchedBranchName.value, branchPage.value + 1, true);
      break;
    case 'billingRefs':
      if (!hasMoreBillingRefs.value || billingRefsLoadingMore.value) return;
      void searchBillingRefsByParams(searchedBillingRef.value, billingRefPage.value + 1, true);
      break;
  }
}

// Debounced wrapper (created once) to avoid recreating debounce on every keypress
const debouncedGetAccounts: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchAccountsByParams(name, 1, false);
});

// Debounced wrapper (created once) to avoid recreating debounce on every keypress
const debouncedGetBranches: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBranchesByParams(name, 1, false);
});

// Debounced wrapper (created once) to avoid recreating debounce on every keypress
const debouncedGetBillingRefs: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBillingRefsByParams(name, 1, false);
});
watch([selectedAccountType, searchedAccountName], async () => {
  accounts.value = [];
  if (selectedAccountType.value != null) {
    if (searchedAccountName.value.length > 0) {
      debouncedGetAccounts(searchedAccountName.value);
    } else {
      await searchAccountsByParams();
    }
  }
}, { immediate: true })
watch([selectedAccount, searchedBranchName, searchedBillingRef], async () => {
  if (selectedAccount.value?.value != null) {
    if (searchedBranchName.value.length > 0) {
      debouncedGetBranches(searchedBranchName.value);
    } else {
      await searchBranchesByParams();
    }

    if (searchedBillingRef.value.length > 0) {
      debouncedGetBillingRefs(searchedBillingRef.value);
    } else {
      await searchBillingRefsByParams();
    }
  }
}, { immediate: true })

watch(soa, (val: Soa | undefined) => {
  if (!val) return;
  if (val.soa_number != null) soaNumber.value = val.soa_number;
  if (val.bill_type != null) selectedBillType.value = String(val.bill_type);
  if (val.status != null) selectedStatus.value = String(val.status);
  if (val.due_date != null) dueDate.value = val.due_date;
  if (val.period_date_from != null) periodDateFrom.value = val.period_date_from;
  if (val.period_date_to != null) periodDateTo.value = val.period_date_to;
  if (val.amount != null) amount.value = String(val.amount);
  if (val.account_code != null) accountCode.value = String(val.account_code);
  if (val.branch_code != null) branchCode.value = String(val.branch_code);
}, { immediate: true })
</script>

<template>
  <form ref="savingForm" class="grid grid-cols-1 md:grid-cols-2 gap-3" enctype="multipart/form-data">
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
          name="user_id"
          :default-value="user?.id"
        />
        <Input
          id="account_code"
          type="hidden"
          class="mt-1 block w-full"
          name="account_code"
          :value="accountCode"
        />
        <Input
          id="branch_code"
          type="hidden"
          class="mt-1 block w-full"
          name="branch_code"
          :value="branchCode"
        />
        <Input
          id="billing_ref"
          type="hidden"
          class="mt-1 block w-full"
          name="billing_ref"
          :value="billingRef"
        />
        <Input type="hidden" name="bill_type" :value="selectedBillType" />
        <Input type="hidden" name="status" :value="selectedStatus" />
    </div>
    <div class="grid gap-2 md:col-span-1">
      <Label for="account_type">Account Type<span class="text-red-400">*</span></Label>
      <Select
        id="account_type"
        class="mt-1 block w-full"
        name="account_type"
        v-model="selectedAccountType"
      >
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

    <div class="md:col-span-1">
      <SearchableCombobox
        id="account"
        label="Account"
        :required="true"
        v-model="accountCode"
        v-model:search="searchedAccountName"
        :items="accounts"
        placeholder="Select Account..."
        search-placeholder="Search Account..."
        empty-text="No account found."
        :disabled="!selectedAccountType"
        :has-more="hasMoreAccounts"
        :loading-more="accountsLoadingMore"
        @load-more="loadMoreData('accounts')"
      />
    </div>

    <div class="md:col-span-1">
      <SearchableCombobox
        id="branch"
        label="Branch"
        v-model="branchCode"
        v-model:search="searchedBranchName"
        :items="branches"
        placeholder="Select Branch..."
        search-placeholder="Search Branch..."
        empty-text="No branch found."
        :disabled="!selectedAccount"
        :has-more="hasMoreBranches"
        :loading-more="branchesLoadingMore"
        @load-more="loadMoreData('branches')"
      />
    </div>

    <div class="md:col-span-1">
      <SearchableCombobox
        id="billing_ref"
        label="Billing Ref"
        v-model="billingRef"
        v-model:search="searchedBillingRef"
        :items="billing_refs"
        placeholder="Select Billing Ref..."
        search-placeholder="Search Billing Ref..."
        empty-text="No Billing Ref found."
        :disabled="!selectedAccount"
        :has-more="hasMoreBillingRefs"
        :loading-more="billingRefsLoadingMore"
        @load-more="loadMoreData('billingRefs')"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="soa_number">SOA Number<span class="text-red-400">*</span></Label>
      <Input
        id="soa_number"
        class="mt-1 block w-full"
        name="soa_number"
        v-model="soaNumber"
        autocomplete="off"
        placeholder="SOA Number"
        :readonly="isReadOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="bill_type">Bill Type<span class="text-red-400">*</span></Label>
      <Select
        id="bill_type"
        class="mt-1 block w-full"
        v-model="selectedBillType"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select bill type" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Bill Type</SelectLabel>
            <SelectItem
              v-for="bt in bill_types"
              :key="bt.value"
              :value="String(bt.value)"
            >
              {{ bt.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="due_date">Due Date<span class="text-red-400">*</span></Label>
      <Input
        id="due_date"
        type="date"
        class="mt-1 block w-full"
        name="due_date"
        v-model="dueDate"
        :readonly="isReadOnly"
      />
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
              v-for="st in status_types"
              :key="st.value"
              :value="String(st.value)"
            >
              {{ st.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="period_date_from">Period Date From</Label>
      <Input
        id="period_date_from"
        type="date"
        class="mt-1 block w-full"
        name="period_date_from"
        v-model="periodDateFrom"
        :readonly="isReadOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="period_date_to">Period Date To</Label>
      <Input
        id="period_date_to"
        type="date"
        class="mt-1 block w-full"
        name="period_date_to"
        v-model="periodDateTo"
        :readonly="isReadOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="amount">Amount<span class="text-red-400">*</span></Label>
      <Input
        id="amount"
        type="number"
        step="0.01"
        class="mt-1 block w-full"
        name="amount"
        v-model="amount"
        placeholder="0.00"
        :readonly="isReadOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="file_pdf">PDF File</Label>
      <Input
        id="file_pdf"
        type="file"
        accept=".pdf"
        class="mt-1 block w-full"
        name="file_pdf"
        :disabled="isReadOnly"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="file_xls">Excel File</Label>
      <Input
        id="file_xls"
        type="file"
        accept=".xls,.xlsx"
        class="mt-1 block w-full"
        name="file_xls"
        :disabled="isReadOnly"
      />
    </div>
  </form>
</template>
