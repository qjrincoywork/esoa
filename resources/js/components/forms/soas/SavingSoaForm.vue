<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { useSoas } from '@/composables/soas';
import { debounce } from '@/composables/utilities/helper';
import { Auth, User, UserDetail, Soa } from '@/types';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type UserBasic = {
  id?: number
  username?: string | number
  email?: string | number
}

type Account = { value: string | number; name: string }
type AccountType = { value: string | number; name: string }
type Branch = { value: string | number; name: string }
type BillingRef = { value: string | number; name: string; balance_raw?: number | string }

const { getAccountsByParams, getBranchesByParams, getBillingRefsByParams } = useSoas();
const props = defineProps({
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
  billing_ref_from_types: {
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
const page = usePage();
const auth = computed(() => (page.props as any).auth as Auth);
const user = computed(() => auth.value?.user as User);
const userDetail = computed(() => user.value?.user_detail as UserDetail);

// Expose a form ref so parent components can access without document.getElementById
const savingForm = ref<HTMLFormElement | null>(null)
const selectedAccountType = ref<string>(soa.value?.account_type != null ? String(soa.value.account_type) : '')
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
const accountCode = ref(soa.value?.account_code ?? '')
const branchCode = ref(soa.value?.branch_code ?? '')
const billingRef = ref<(string | number)[]>(soa.value?.billing_ref != null
  ? String(soa.value.billing_ref).split(',').filter(Boolean)
  : [])
const searchedAccountName = ref('')
const soaNumber = ref(soa.value?.soa_number ?? '')
const selectedBillType = ref<string | number>(soa.value?.bill_type ?? '')
const selectedStatus = ref<string | number>(soa.value?.status ?? '1')
const selectedBillRefFrom = ref<string | number>(soa.value?.billing_ref_from ?? '')
const dueDate = ref(soa.value?.due_date ?? '')
const periodDateFrom = ref(soa.value?.period_date_from ?? '')
const periodDateTo = ref(soa.value?.period_date_to ?? '')
const amount = ref(soa.value?.amount != null ? String(soa.value.amount) : '')
const billingDateFrom = ref('')
const billingDateTo = ref('')
const hasBillingRefValue = ref(soa.value?.billing_ref != null && billingRef.value.length > 0)
const hasBillingRef = ref(soa.value?.id ? true : hasBillingRefValue.value)
const searchedBranchName = ref('')
const searchedBillingRef = ref('')
const isSyncingFromSoa = ref(false)
const isEndorsed = computed(() => soa.value?.status == 2)
const selectedAccount = computed(() =>
  accounts.value?.find(account => String(account.value) === accountCode.value),
)
const selectedBillingRefsTotal = computed(() => {
  if (!billingRef.value.length || !billing_refs.value.length) return 0

  const selectedValues = new Set(billingRef.value.map((value: string | number) => String(value)))
  return billing_refs.value.reduce((sum: number, item: BillingRef) => {
    if (!selectedValues.has(String(item.value))) return sum
    const numericBalance = Number(item.balance_raw ?? 0)
    return Number.isFinite(numericBalance) ? sum + numericBalance : sum
  }, 0)
})

function fileBasename(path: string): string {
  const normalized = path.replace(/\\/g, '/')
  const segment = normalized.split('/').pop()
  return segment || path
}

/** Existing uploads (native file inputs cannot show these; we show name + link instead). */
const existingPdf = computed(() => {
  const id = soa.value?.id
  const path = soa.value?.file_pdf
  if (id == null || !path) return null
  return { name: fileBasename(String(path)), href: `/soas/${id}/attachment/pdf` }
})

const existingExcel = computed(() => {
  const id = soa.value?.id
  const path = soa.value?.file_xls
  if (id == null || !path) return null
  return { name: fileBasename(String(path)), href: `/soas/${id}/attachment/excel` }
})
const filteredStatusTypes = computed(() => {
  if (userDetail.value?.employee_no) {
    return status_types.value?.filter(s => s.value !== 2)
  }
  return status_types.value?.filter(s => s.value == 2)
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

// Fetch accounts by selected type, optional search name, and page (replace or append)
const searchAccountsByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccountType.value) {
    accounts.value = [];
    return;
  }
  if (append) {
    accountsLoadingMore.value = true;
  }
  const params = {
    type: selectedAccountType.value,
    name,
    page,
  };
  if (accountCode.value != null) {
    params.selected_code = accountCode.value;
  }
  const result = await getAccountsByParams(params);

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
  const params = {
    account_code: selectedAccount.value?.value,
    name,
    page,
  };
  if (branchCode.value != null) {
    params.selected_code = branchCode.value;
  }
  const result = await getBranchesByParams(params);
  if (append) {
    branches.value = [...(branches.value ?? []), ...result?.data];
  } else {
    branches.value = result?.data ?? [];
  }
  branchPage.value = result?.current_page;
  branchLastPage.value = result?.last_page;
  branchesLoadingMore.value = false;
}

// Fetch billing refs by selected account, optional search name, and page (replace or append)
const searchBillingRefsByParams = async (name = '', page = 1, append = false) => {
  if (!accountCode.value) {
    billing_refs.value = [];
    return;
  }
  if (append) {
    billingRefsLoadingMore.value = true;
  }

  // Pass all selected refs as comma-separated string for multiple selection
  const selectedRefsParam = billingRef.value.length ? billingRef.value.join(',') : null;
  const params = {
    account_type: selectedAccountType.value,
    account_code: accountCode.value,
    billing_ref_from: selectedBillRefFrom.value,
    page,
  };
  if (billingDateFrom.value != '' && billingDateTo.value != '') {
    params.billing_date_from = billingDateFrom.value ?? '';
    params.billing_date_to = billingDateTo.value ?? '';
  }
  if (selectedRefsParam != null) {
    params.billing_refs = selectedRefsParam;
  }
  if (name != null && name.trim() !== '') {
    params.name = name.trim();
  }
  const result = await getBillingRefsByParams(params);

  if (append) {
    billing_refs.value = [...(billing_refs.value ?? []), ...result?.data];
  } else {
    billing_refs.value = result?.data ?? [];
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
}, 2000);

// Debounced wrapper (created once) to avoid recreating debounce on every keypress
const debouncedGetBranches: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBranchesByParams(name, 1, false);
}, 2000);

// Debounced wrapper (created once) to avoid recreating debounce on every keypress
const debouncedGetBillingRefs: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBillingRefsByParams(name, 1, false);
}, 2000);
watch([selectedAccountType, searchedAccountName], async () => {
  if (!isSyncingFromSoa.value) {
    accounts.value = [];
  }
  if (selectedAccountType.value) {
    if (searchedAccountName.value.length > 0) {
      debouncedGetAccounts(searchedAccountName.value);
    } else {
      await searchAccountsByParams();
    }
  }
}, { immediate: true })
watch([selectedAccount, searchedBranchName], async () => {
  if (selectedAccount.value?.value != null) {
    if (searchedBranchName.value.length > 0) {
      debouncedGetBranches(searchedBranchName.value);
    } else {
      await searchBranchesByParams();
    }
  }
}, { immediate: true })
watch([searchedBillingRef, selectedBillRefFrom], async () => {
  if (selectedBillRefFrom.value != null) {
    if (searchedBillingRef.value.length > 0) {
      debouncedGetBillingRefs(searchedBillingRef.value);
    } else {
      await searchBillingRefsByParams();
    }
  }
}, { immediate: true })

// Reset dependent fields when switching account type or account
watch(selectedAccountType, () => {
  if (isSyncingFromSoa.value) return
  accountCode.value = ''
  branchCode.value = ''
  billingRef.value = []
  branches.value = []
  billing_refs.value = []
  searchedAccountName.value = ''
  searchedBranchName.value = ''
  searchedBillingRef.value = ''
})
watch(accountCode, () => {
  if (isSyncingFromSoa.value) return
  branchCode.value = ''
  billingRef.value = []
  branches.value = []
  billing_refs.value = []
  searchedBranchName.value = ''
  searchedBillingRef.value = ''
})
watch(selectedBillingRefsTotal, (total: number) => {
  amount.value = total > 0 ? total.toFixed(2) : ''
}, { immediate: true })

watch(soa, (val: Soa | undefined) => {
  if (!val) return;
  isSyncingFromSoa.value = true
  if (val.soa_number != null) soaNumber.value = val.soa_number;
  if (val.account_type != null) selectedAccountType.value = String(val.account_type);
  if (val.bill_type != null) selectedBillType.value = String(val.bill_type);
  if (val.status != null) selectedStatus.value = String(val.status);
  if (val.due_date != null) dueDate.value = val.due_date;
  if (val.period_date_from != null) periodDateFrom.value = val.period_date_from;
  if (val.period_date_to != null) periodDateTo.value = val.period_date_to;
  if (val.amount != null) amount.value = String(val.amount);
  if (val.account_code != null) accountCode.value = String(val.account_code);
  if (val.branch_code != null) branchCode.value = String(val.branch_code);
  if (val.billing_ref != null) billingRef.value = String(val.billing_ref).split(',').filter(Boolean);
  if (val.billing_ref_from != null) selectedBillRefFrom.value = val.billing_ref_from;
  isSyncingFromSoa.value = false
}, { immediate: true })
</script>

<template>
  <form ref="savingForm" class="flex flex-col gap-5" enctype="multipart/form-data">
    <!-- Hidden inputs: keep FormData in sync with reactive state -->
    <div class="hidden">
      <input type="hidden" name="id" :value="soa?.id ?? ''" />
      <input type="hidden" name="account_type" :value="selectedAccountType ?? ''" />
      <input type="hidden" name="account_code" :value="accountCode ?? ''" />
      <input type="hidden" name="branch_code" :value="branchCode ?? ''" />
      <input type="hidden" name="billing_ref" :value="billingRef.length ? JSON.stringify(billingRef) : ''" />
      <input type="hidden" name="bill_type" :value="String(selectedBillType ?? '')" />
      <input type="hidden" name="status" :value="String(selectedStatus ?? '')" />
      <input type="hidden" name="billing_ref_from" :value="String(selectedBillRefFrom ?? '')" />
    </div>

    <!-- Section 1: Account Information -->
    <section v-if="!isEndorsed" class="rounded-lg border p-4 flex flex-col gap-4">
      <h3 class="text-sm font-semibold">Account Information</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="grid gap-2">
          <Label for="account_type">Account Type<span class="text-red-400">*</span></Label>
          <Select id="account_type" v-model="selectedAccountType">
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
    </section>

    <!-- Section 2: Billing Reference -->
    <section class="rounded-lg border p-4 flex flex-col gap-4">
      <div class="flex items-center gap-3">
        <Checkbox id="has_billing_ref" v-model="hasBillingRef" class="cursor-pointer" />
        <Label for="has_billing_ref" class="cursor-pointer font-semibold">Has Billing Reference?</Label>
      </div>

      <div v-if="hasBillingRef" class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="grid gap-2">
          <Label for="billing_date_from">Billing Date From</Label>
          <Input
            id="billing_date_from"
            type="date"
            class="w-full"
            name="billing_date_from"
            v-model="billingDateFrom"
          />
        </div>

        <div class="grid gap-2">
          <Label for="billing_date_to">Billing Date To</Label>
          <Input
            id="billing_date_to"
            type="date"
            class="w-full"
            name="billing_date_to"
            v-model="billingDateTo"
          />
        </div>

        <div class="grid gap-2">
          <Label for="billing_ref_from">Billing Reference From<span class="text-red-400">*</span></Label>
          <Select id="billing_ref_from" v-model="selectedBillRefFrom" :disabled="!selectedAccount">
            <SelectTrigger class="w-full">
              <SelectValue placeholder="Select a billing reference from" />
            </SelectTrigger>
            <SelectContent class="w-full">
              <SelectGroup>
                <SelectLabel>Billing Reference From</SelectLabel>
                <SelectItem
                  v-for="billing_ref_from_type in billing_ref_from_types"
                  :key="billing_ref_from_type.value"
                  :value="String(billing_ref_from_type.value)"
                >
                  {{ billing_ref_from_type.name }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <SearchableCombobox
          id="billing_ref"
          label="Billing Reference"
          v-model="billingRef"
          v-model:search="searchedBillingRef"
          :items="billing_refs"
          placeholder="Select Billing Reference..."
          search-placeholder="Search Billing Reference..."
          empty-text="No Billing Reference found."
          :disabled="billing_refs?.length == 0"
          :has-more="hasMoreBillingRefs"
          :loading-more="billingRefsLoadingMore"
          :multiple="true"
          @load-more="loadMoreData('billingRefs')"
        />
      </div>
    </section>

    <!-- Section 3: SOA Details -->
    <section class="rounded-lg border p-4 flex flex-col gap-4">
      <h3 class="text-sm font-semibold">SOA Details</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="soa_number">SOA Number / Billing Invoice<span class="text-red-400">*</span></Label>
          <Input
            id="soa_number"
            class="w-full"
            name="soa_number"
            v-model="soaNumber"
            autocomplete="off"
            placeholder="SOA Number / Billing Invoice"
          />
        </div>

        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="bill_type">Bill Type<span class="text-red-400">*</span></Label>
          <Select id="bill_type" v-model="selectedBillType">
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

        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="due_date">Due Date<span class="text-red-400">*</span></Label>
          <Input
            id="due_date"
            type="date"
            class="w-full"
            name="due_date"
            v-model="dueDate"
          />
        </div>

        <div class="grid gap-2">
          <Label for="status">Status<span class="text-red-400">*</span></Label>
          <Select id="status" v-model="selectedStatus">
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

        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="period_date_from">Period Date From</Label>
          <Input
            id="period_date_from"
            type="date"
            class="w-full"
            name="period_date_from"
            v-model="periodDateFrom"
          />
        </div>

        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="period_date_to">Period Date To</Label>
          <Input
            id="period_date_to"
            type="date"
            class="w-full"
            name="period_date_to"
            v-model="periodDateTo"
          />
        </div>

        <div v-if="!isEndorsed" class="grid gap-2">
          <Label for="amount">Amount<span class="text-red-400">*</span></Label>
          <Input
            id="amount"
            type="number"
            step="0.01"
            class="w-full"
            name="amount"
            v-model="amount"
            placeholder="0.00"
          />
        </div>

        <div class="grid gap-2">
          <Label for="file_pdf">PDF File</Label>
          <p v-if="existingPdf" class="text-xs text-[var(--color-text-muted)]">
            Current:
            <a
              :href="existingPdf.href"
              target="_blank"
              rel="noopener noreferrer"
              class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
            >{{ existingPdf.name }}</a>
          </p>
          <Input
            :key="`pdf-${soa?.id ?? 'new'}`"
            id="file_pdf"
            type="file"
            accept=".pdf"
            class="w-full"
            name="file_pdf"
          />
        </div>

        <div class="grid gap-2">
          <Label for="file_xls">Excel File</Label>
          <p v-if="existingExcel" class="text-xs text-[var(--color-text-muted)]">
            Current:
            <a
              :href="existingExcel.href"
              target="_blank"
              rel="noopener noreferrer"
              class="cursor-pointer font-medium text-[var(--color-text)] underline underline-offset-2 hover:opacity-90"
            >{{ existingExcel.name }}</a>
          </p>
          <Input
            :key="`xls-${soa?.id ?? 'new'}`"
            id="file_xls"
            type="file"
            accept=".xls,.xlsx"
            class="w-full"
            name="file_xls"
          />
        </div>
      </div>
    </section>
  </form>
</template>
