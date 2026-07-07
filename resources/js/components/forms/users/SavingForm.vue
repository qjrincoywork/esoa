<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { useUsers } from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import Switch from '@/components/ui/switch/Switch.vue';
import UserAccountsList from '@/components/forms/users/UserAccountsList.vue';

type Type = { value: string | number; name: string }
type AccountType = { value: string | number; name: string }
type Account = { value: string | number; name: string }
type Branch = { value: string | number; name: string }
type Gender = { value: string | number; name: string }
type CivilStatus = { id: string | number; name: string }
type Citizenship = { id: string | number; name: string }
type Department = { id: string | number; name: string }
type Position = { id: string | number; name: string }
type Role = { id: string | number; name?: string; guard_name?: string }

type SelectedUserAccount = {
  account_type: string
  account_code: string
  account_name: string
  branch_code: string
  branch_name: string
}

type UserDetail = {
  type?: number
  agent_code?: string
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
  user_accounts?: Array<{ account_type?: string; account_code?: string; branch_code?: string }>
  roles?: Array<{ id?: string | number }>
}

const props = defineProps({
  user: {
    type: Object as unknown as () => UserBasic,
    default: () => [],
  },
  genders: {
    type: Array as unknown as () => Gender[],
    required: true,
    default: () => [],
  },
  types: {
    type: Array as unknown as () => Type[],
    required: true,
    default: () => [],
  },
  account_types: {
    type: Array as unknown as () => AccountType[],
    required: true,
    default: () => [],
  },
  civil_statuses: {
    type: Array as unknown as () => CivilStatus[],
    required: true,
    default: () => [],
  },
  citizenships: {
    type: Array as unknown as () => Citizenship[],
    required: true,
    default: () => [],
  },
  departments: {
    type: Array as unknown as () => Department[],
    required: true,
    default: () => [],
  },
  positions: {
    type: Array as unknown as () => Position[],
    required: true,
    default: () => [],
  },
  all_roles: {
    type: Array as unknown as () => Role[],
    required: false,
    default: () => [],
  },
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

const user   = computed<UserBasic>(() => props.user as UserBasic)
const detail = computed<UserDetail>(() => user.value?.user_detail as UserDetail)

const genders       = computed<Gender[]>(() => props.genders as Gender[]);
const civil_statuses = computed<CivilStatus[]>(() => props.civil_statuses as CivilStatus[]);
const citizenships  = computed<Citizenship[]>(() => props.citizenships as Citizenship[]);
const departments   = computed<Department[]>(() => props.departments as Department[]);
const positions     = computed<Position[]>(() => props.positions as Position[]);
const account_types = computed<AccountType[]>(() => props.account_types as AccountType[]);
const types         = computed<Type[]>(() => props.types as Type[]);
const all_roles     = computed<Role[]>(() => props.all_roles as Role[]);

// Roles selected on load (edit mode preselects the user's current roles).
const roleSearch      = ref('')
const selectedRoleIds = ref<string[]>(
  (user.value?.roles ?? []).map((r: { id?: string | number }) => String(r.id))
)
const filteredRoles = computed<Role[]>(() => {
  const term = roleSearch.value.toLowerCase().trim()
  if (!term) return all_roles.value
  return all_roles.value.filter((r: Role) => String(r.name ?? '').toLowerCase().includes(term))
})
const isRoleChecked = (id: string | number) => selectedRoleIds.value.includes(String(id))

const userType = ref(detail.value?.type != null ? Number(detail.value.type) : 1)
const userId   = ref(user?.value?.id ?? null)

// Sync from props when user/detail loads (e.g. edit mode with async data)
watch(
  () => detail.value?.type,
  (val: number | undefined) => { if (val != null) userType.value = Number(val) },
  { immediate: true },
)

// ─── ACCOUNT_BRANCH_ADMIN (type 2) single account state ───────────────────
const selectedAccountType = ref<string>(
  (user.value?.user_accounts?.[0]?.account_type != null)
    ? String(user.value.user_accounts[0].account_type)
    : ''
)
const accountCode  = ref<string>(user.value?.user_accounts?.[0]?.account_code ? String(user.value.user_accounts[0].account_code) : '')
const branchCode   = ref<string>(user.value?.user_accounts?.[0]?.branch_code  ? String(user.value.user_accounts[0].branch_code)  : '')

const accounts             = ref<Account[]>([])
const branches             = ref<Branch[]>([])
const accountPage          = ref(1)
const accountLastPage      = ref(1)
const branchPage           = ref(1)
const branchLastPage       = ref(1)
const accountsLoadingMore  = ref(false)
const branchesLoadingMore  = ref(false)
const hasMoreAccounts      = computed(() => accountPage.value < accountLastPage.value)
const hasMoreBranches      = computed(() => branchPage.value < branchLastPage.value)
const searchedAccountName  = ref('')
const searchedBranchName   = ref('')
const isSyncing            = ref(false)
const selectedAccount      = computed(() =>
  accounts.value?.find((a: Account) => String(a.value) === accountCode.value)
)

// ─── GROUP_ACCOUNT_ADMIN (type 4) multi-account state ─────────────────────
const selectedUserAccounts = ref<SelectedUserAccount[]>(
  (user.value?.user_accounts ?? []).map((ua: { account_type?: string | null; account_code?: string; branch_code?: string | null }) => ({
    account_type: ua.account_type ?? '',
    account_code: String(ua.account_code ?? ''),
    account_name: String(ua.account_code ?? ''),
    branch_code:  String(ua.branch_code  ?? ''),
    branch_name:  String(ua.branch_code  ?? ''),
  }))
)

// "Add entry" form state for GROUP_ACCOUNT_ADMIN
const newAccountType       = ref<string>('')
const newAccountCode       = ref<string>('')
const newBranchCode        = ref<string>('')
const newAccounts          = ref<Account[]>([])
const newBranches          = ref<Branch[]>([])
const newAccountPage       = ref(1)
const newAccountLastPage   = ref(1)
const newBranchPage        = ref(1)
const newBranchLastPage    = ref(1)
const newAccountsLoading   = ref(false)
const newBranchesLoading   = ref(false)
const hasMoreNewAccounts   = computed(() => newAccountPage.value < newAccountLastPage.value)
const hasMoreNewBranches   = computed(() => newBranchPage.value < newBranchLastPage.value)
const searchedNewAccount   = ref('')
const searchedNewBranch    = ref('')
const duplicateError       = ref('')
const newSelectedAccount   = computed(() =>
  newAccounts.value.find((a: Account) => String(a.value) === newAccountCode.value)
)

const { getAccountsByParams, getBranchesByParams, getUsersWithAccounts } = useUsers();

// ─── Copy access from another user (GROUP_ACCOUNT_ADMIN) ──────────────────
type CopyableUser = { value: string | number; name: string; accounts?: Array<{ account_type?: string | null; account_code?: string | null; branch_code?: string | null }> }
const copyUsers            = ref<CopyableUser[]>([])
const copySourceUserId     = ref<string>('')
const searchedCopyUser     = ref('')
const copyUserPage         = ref(1)
const copyUserLastPage     = ref(1)
const copyUsersLoadingMore = ref(false)
const hasMoreCopyUsers     = computed(() => copyUserPage.value < copyUserLastPage.value)
const copyMessage          = ref('')
const copySelectedUser     = computed(() =>
  copyUsers.value.find((u: CopyableUser) => String(u.value) === copySourceUserId.value)
)

// ─── Form ref ─────────────────────────────────────────────────────────────
const userEditForm = ref<HTMLFormElement | null>(null)

function getFormData(): FormData | null {
  if (!userEditForm.value) return null
  return new FormData(userEditForm.value)
}

defineExpose({ userEditForm, getFormData })
onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: userEditForm.value })
  }
})

// ─── Single-account fetch helpers (ACCOUNT_BRANCH_ADMIN) ──────────────────
const searchAccountsByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccountType.value) { accounts.value = []; return; }
  if (append) accountsLoadingMore.value = true;
  const result = await getAccountsByParams({
    type: selectedAccountType.value, name, page,
    selected_code: accountCode.value || undefined,
  });
  accounts.value = append ? [...(accounts.value ?? []), ...(result?.data ?? [])] : (result?.data ?? []);
  accountPage.value     = result?.current_page ?? 1;
  accountLastPage.value = result?.last_page ?? 1;
  accountsLoadingMore.value = false;
}

const searchBranchesByParams = async (name = '', page = 1, append = false) => {
  if (!selectedAccount.value?.value) { branches.value = []; return; }
  if (append) branchesLoadingMore.value = true;
  const result = await getBranchesByParams({
    account_code: selectedAccount.value?.value, name, page,
    selected_code: branchCode.value || undefined,
  });
  branches.value = append ? [...(branches.value ?? []), ...(result?.data ?? [])] : (result?.data ?? []);
  branchPage.value     = result?.current_page ?? 1;
  branchLastPage.value = result?.last_page ?? 1;
  branchesLoadingMore.value = false;
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
  }
}

const debouncedGetAccounts: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchAccountsByParams(name, 1, false);
});
const debouncedGetBranches: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchBranchesByParams(name, 1, false);
});

// ─── Group-account "Add entry" fetch helpers ──────────────────────────────
const searchNewAccountsByParams = async (name = '', page = 1, append = false) => {
  if (!newAccountType.value) { newAccounts.value = []; return; }
  if (append) newAccountsLoading.value = true;
  const result = await getAccountsByParams({ type: newAccountType.value, name, page });
  newAccounts.value = append ? [...newAccounts.value, ...(result?.data ?? [])] : (result?.data ?? []);
  newAccountPage.value     = result?.current_page ?? 1;
  newAccountLastPage.value = result?.last_page ?? 1;
  newAccountsLoading.value = false;
}

const searchNewBranchesByParams = async (name = '', page = 1, append = false) => {
  if (!newSelectedAccount.value?.value) { newBranches.value = []; return; }
  if (append) newBranchesLoading.value = true;
  const result = await getBranchesByParams({ account_code: newSelectedAccount.value?.value, name, page });
  newBranches.value = append ? [...newBranches.value, ...(result?.data ?? [])] : (result?.data ?? []);
  newBranchPage.value     = result?.current_page ?? 1;
  newBranchLastPage.value = result?.last_page ?? 1;
  newBranchesLoading.value = false;
}

function loadMoreNewData(input: string) {
  switch (input) {
    case 'accounts':
      if (!hasMoreNewAccounts.value || newAccountsLoading.value) return;
      void searchNewAccountsByParams(searchedNewAccount.value, newAccountPage.value + 1, true);
      break;
    case 'branches':
      if (!hasMoreNewBranches.value || newBranchesLoading.value) return;
      void searchNewBranchesByParams(searchedNewBranch.value, newBranchPage.value + 1, true);
      break;
  }
}

const debouncedGetNewAccounts: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchNewAccountsByParams(name, 1, false);
});
const debouncedGetNewBranches: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchNewBranchesByParams(name, 1, false);
});

// ─── Copy-access "pick user" fetch helpers ─────────────────────────────────
const searchCopyUsers = async (name = '', page = 1, append = false) => {
  if (append) copyUsersLoadingMore.value = true;
  const result = await getUsersWithAccounts({
    name, page,
    exclude_id: userId.value || '',
  });
  copyUsers.value = append ? [...copyUsers.value, ...(result?.data ?? [])] : (result?.data ?? []);
  copyUserPage.value     = result?.current_page ?? 1;
  copyUserLastPage.value = result?.last_page ?? 1;
  copyUsersLoadingMore.value = false;
}

function loadMoreCopyUsers() {
  if (!hasMoreCopyUsers.value || copyUsersLoadingMore.value) return;
  void searchCopyUsers(searchedCopyUser.value, copyUserPage.value + 1, true);
}

const debouncedGetCopyUsers: (...args: any[]) => void = debounce((evOrName?: any) => {
  const name = typeof evOrName === 'string' ? evOrName : (evOrName?.target?.value ?? '');
  void searchCopyUsers(name, 1, false);
});

/**
 * Add a single account/branch entry, skipping exact account+branch duplicates.
 * Returns true when the entry was added, false when it already existed.
 */
function tryAddUserAccount(entry: SelectedUserAccount): boolean {
  const isDuplicate = selectedUserAccounts.value.some(
    (ua: SelectedUserAccount) => ua.account_code === entry.account_code && ua.branch_code === entry.branch_code
  );
  if (isDuplicate) return false;
  selectedUserAccounts.value.push(entry);
  return true;
}

function addUserAccount() {
  if (!newAccountCode.value) return;
  const account = newAccounts.value.find((a: Account) => String(a.value) === newAccountCode.value);
  const branch  = newBranches.value.find((b: Branch)  => String(b.value) === newBranchCode.value);
  const added = tryAddUserAccount({
    account_type: newAccountType.value,
    account_code: newAccountCode.value,
    account_name: account?.name ?? newAccountCode.value,
    branch_code:  newBranchCode.value,
    branch_name:  branch?.name  ?? newBranchCode.value,
  });
  if (!added) {
    duplicateError.value = 'This account / branch combination has already been added.';
    return;
  }
  duplicateError.value = '';
  // Reset picker
  newAccountType.value   = '';
  newAccountCode.value   = '';
  newBranchCode.value    = '';
  newAccounts.value      = [];
  newBranches.value      = [];
  searchedNewAccount.value = '';
  searchedNewBranch.value  = '';
}

/**
 * Copy every account/branch entry from the selected source user into the
 * current list, skipping duplicates. Source rows only carry codes, so the
 * code doubles as the display name (matching how edit-mode initialises).
 */
function copyUserAccounts() {
  const source = copySelectedUser.value;
  if (!source) return;

  let added = 0;
  let skipped = 0;
  for (const ua of source.accounts ?? []) {
    if (!ua.account_code) continue;
    const ok = tryAddUserAccount({
      account_type: ua.account_type ?? '',
      account_code: String(ua.account_code),
      account_name: String(ua.account_code),
      branch_code:  String(ua.branch_code ?? ''),
      branch_name:  String(ua.branch_code ?? ''),
    });
    ok ? added++ : skipped++;
  }

  copyMessage.value = added > 0
    ? `Copied ${added} account${added !== 1 ? 's' : ''}${skipped ? `, ${skipped} already present` : ''}.`
    : 'All of that user’s accounts are already added.';

  // Reset picker
  copySourceUserId.value = '';
  searchedCopyUser.value = '';
}

function removeUserAccount(index: number) {
  selectedUserAccounts.value.splice(index, 1);
}

// ─── Watchers ─────────────────────────────────────────────────────────────
// Reset ACCOUNT_BRANCH_ADMIN dependent fields on account type change
watch(selectedAccountType, () => {
  if (isSyncing.value) return;
  accountCode.value        = '';
  branchCode.value         = '';
  branches.value           = [];
  searchedAccountName.value = '';
  searchedBranchName.value  = '';
})
watch(accountCode, () => {
  if (isSyncing.value) return;
  branchCode.value         = '';
  branches.value           = [];
  searchedBranchName.value = '';
})
watch([selectedAccountType, searchedAccountName], async () => {
  accounts.value = [];
  if (selectedAccountType.value != null) {
    searchedAccountName.value.length > 0
      ? debouncedGetAccounts(searchedAccountName.value)
      : await searchAccountsByParams();
  }
}, { immediate: true })
watch([selectedAccount, searchedBranchName], async () => {
  if (selectedAccount.value?.value != null) {
    searchedBranchName.value.length > 0
      ? debouncedGetBranches(searchedBranchName.value)
      : await searchBranchesByParams();
  }
}, { immediate: true })

// Reset GROUP_ACCOUNT_ADMIN picker fields when account type changes
watch(newAccountType, () => {
  duplicateError.value     = '';
  newAccountCode.value     = '';
  newBranchCode.value      = '';
  newBranches.value        = [];
  searchedNewAccount.value = '';
  searchedNewBranch.value  = '';
})
watch(newAccountCode, () => {
  duplicateError.value     = '';
  newBranchCode.value      = '';
  newBranches.value        = [];
  searchedNewBranch.value  = '';
})
watch(newBranchCode, () => { duplicateError.value = ''; })
watch([newAccountType, searchedNewAccount], async () => {
  newAccounts.value = [];
  if (newAccountType.value) {
    searchedNewAccount.value.length > 0
      ? debouncedGetNewAccounts(searchedNewAccount.value)
      : await searchNewAccountsByParams();
  }
}, { immediate: true })
watch([newSelectedAccount, searchedNewBranch], async () => {
  if (newSelectedAccount.value?.value) {
    searchedNewBranch.value.length > 0
      ? debouncedGetNewBranches(searchedNewBranch.value)
      : await searchNewBranchesByParams();
  }
}, { immediate: true })

// Load / search copyable users only while the GROUP_ACCOUNT_ADMIN panel is shown
watch([userType, searchedCopyUser], async () => {
  if (userType.value !== 4) return;
  searchedCopyUser.value.length > 0
    ? debouncedGetCopyUsers(searchedCopyUser.value)
    : await searchCopyUsers();
}, { immediate: true })
</script>

<template>
  <form ref="userEditForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <!-- Hidden base fields -->
    <div class="md:col-span-2 hidden">
      <input type="hidden" name="id"           :value="userId ?? ''" />
      <!-- ACCOUNT_BRANCH_ADMIN single-account hidden inputs -->
      <template v-if="userType === 2">
        <input type="hidden" name="account_type" :value="selectedAccountType ?? ''" />
        <input type="hidden" name="account_code" :value="accountCode ?? ''" />
        <input type="hidden" name="branch_code"  :value="branchCode ?? ''" />
      </template>
      <!-- GROUP_ACCOUNT_ADMIN multi-account hidden inputs -->
      <template v-if="userType === 4">
        <template v-for="(ua, index) in selectedUserAccounts" :key="index">
          <input type="hidden" :name="`user_accounts[${index}][account_type]`" :value="ua.account_type" />
          <input type="hidden" :name="`user_accounts[${index}][account_code]`" :value="ua.account_code" />
          <input type="hidden" :name="`user_accounts[${index}][branch_code]`"  :value="ua.branch_code"  />
        </template>
      </template>
    </div>

    <!-- User Type -->
    <div class="grid gap-2 md:col-span-1">
      <Label for="type">User Type<span class="text-red-400">*</span></Label>
      <Select id="type" class="mt-1 block w-full" name="type" :default-value="userType" v-model="userType">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select User type" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>User Type</SelectLabel>
            <SelectItem v-for="type in types" :key="type.value" :value="Number(type.value)">
              {{ type.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <!-- ── ACCOUNT_BRANCH_ADMIN (type 2): single account/branch ─────────── -->
    <div v-if="userType === 2" class="grid gap-2 md:col-span-1">
      <Label for="account_type">Account Type<span class="text-red-400">*</span></Label>
      <Select id="account_type" class="mt-1 block w-full" v-model="selectedAccountType">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select an account type" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Account Type</SelectLabel>
            <SelectItem v-for="at in account_types" :key="at.value" :value="String(at.value)">
              {{ at.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="userType === 2" class="md:col-span-1">
      <SearchableCombobox
        id="account" label="Account" :required="true"
        v-model="accountCode" v-model:search="searchedAccountName"
        :items="accounts" placeholder="Select Account..."
        search-placeholder="Search Account..." empty-text="No account found."
        :disabled="!selectedAccountType" :has-more="hasMoreAccounts"
        :loading-more="accountsLoadingMore" @load-more="loadMoreData('accounts')"
      />
    </div>

    <div v-if="userType === 2" class="md:col-span-1">
      <SearchableCombobox
        id="branch" label="Branch"
        v-model="branchCode" v-model:search="searchedBranchName"
        :items="branches" placeholder="Select Branch..."
        search-placeholder="Search Branch..." empty-text="No branch found."
        :disabled="!selectedAccount" :has-more="hasMoreBranches"
        :loading-more="branchesLoadingMore" @load-more="loadMoreData('branches')"
      />
    </div>

    <!-- ── GROUP_ACCOUNT_ADMIN (type 4): multiple account/branch pairs ───── -->
    <div v-if="userType === 4" class="md:col-span-2 flex flex-col gap-3">

      <!-- Picker row -->
      <div class="rounded-lg border p-4 flex flex-col gap-3">
        <p class="text-sm font-medium">Add Account / Branch Access</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="grid gap-2">
            <Label for="new_account_type">Account Type</Label>
            <Select id="new_account_type" v-model="newAccountType">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="Select account type" />
              </SelectTrigger>
              <SelectContent class="w-full">
                <SelectGroup>
                  <SelectLabel>Account Type</SelectLabel>
                  <SelectItem v-for="at in account_types" :key="at.value" :value="String(at.value)">
                    {{ at.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <SearchableCombobox
            id="new_account" label="Account" :required="false"
            v-model="newAccountCode" v-model:search="searchedNewAccount"
            :items="newAccounts" placeholder="Select Account..."
            search-placeholder="Search Account..." empty-text="No account found."
            :disabled="!newAccountType" :has-more="hasMoreNewAccounts"
            :loading-more="newAccountsLoading" @load-more="loadMoreNewData('accounts')"
          />

          <SearchableCombobox
            id="new_branch" label="Branch" :required="false"
            v-model="newBranchCode" v-model:search="searchedNewBranch"
            :items="newBranches" placeholder="Select Branch..."
            search-placeholder="Search Branch..." empty-text="No branch found."
            :disabled="!newSelectedAccount" :has-more="hasMoreNewBranches"
            :loading-more="newBranchesLoading" @load-more="loadMoreNewData('branches')"
          />
        </div>
        <div>
          <Button type="button" :disabled="!newAccountCode" @click="addUserAccount">
            + Add
          </Button>
        </div>
        <p v-if="duplicateError" class="text-sm text-red-500">{{ duplicateError }}</p>
      </div>

      <!-- Copy access from an existing user -->
      <div class="rounded-lg border p-4 flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <p class="text-sm font-medium">Copy Access From Another User</p>
          <p class="text-xs text-muted-foreground">
            Pull an existing user's account/branch access into the list below. Duplicates are skipped.
          </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3 md:items-end">
          <SearchableCombobox
            id="copy_user" label="User" :required="false"
            v-model="copySourceUserId" v-model:search="searchedCopyUser"
            :items="copyUsers" placeholder="Select a user..."
            search-placeholder="Search by username or email..." empty-text="No users with access found."
            :has-more="hasMoreCopyUsers" :loading-more="copyUsersLoadingMore"
            @load-more="loadMoreCopyUsers"
          />
          <Button type="button" :disabled="!copySelectedUser" @click="copyUserAccounts">
            Copy Accounts
          </Button>
        </div>
        <p v-if="copyMessage" class="text-sm text-emerald-600 dark:text-emerald-400">{{ copyMessage }}</p>
      </div>

      <!-- Selected list -->
      <UserAccountsList
        :items="selectedUserAccounts"
        :account-types="account_types"
        @remove="removeUserAccount"
      />
    </div>

    <!-- ── VC Employee (type 1) ──────────────────────────────────────────── -->
    <div v-if="userType === 1" class="grid gap-2 md:col-span-1">
      <Label for="department">Department<span class="text-red-400">*</span></Label>
      <Select id="department" class="mt-1 block w-full" name="department_id"
        :default-value="detail?.department_id ? Number(detail?.department_id) : undefined">
        <SelectTrigger class="w-full"><SelectValue placeholder="Select a department" /></SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Department</SelectLabel>
            <SelectItem v-for="department in departments" :key="department.id" :value="Number(department.id)">
              {{ department.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="userType === 1" class="grid gap-2 md:col-span-1">
      <Label for="position">Position<span class="text-red-400">*</span></Label>
      <Select id="position" class="mt-1 block w-full" name="position_id"
        :default-value="detail?.position_id ? String(detail?.position_id) : undefined">
        <SelectTrigger class="w-full"><SelectValue placeholder="Select a position" /></SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Position</SelectLabel>
            <SelectItem v-for="position in positions" :key="position.id" :value="String(position.id)">
              {{ position.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="userType === 1" class="grid gap-2 md:col-span-1">
      <Label for="employee_no">Employee No<span class="text-red-400">*</span></Label>
      <Input id="employee_no" class="mt-1 block w-full" name="employee_no"
        :default-value="detail?.employee_no" autocomplete="employee_no" placeholder="Employee No" />
    </div>

    <!-- ── Broker (type 3) ───────────────────────────────────────────────── -->
    <div v-if="userType === 3" class="grid gap-2 md:col-span-1">
      <Label for="agent_code">Agent Code<span class="text-red-400">*</span></Label>
      <Input id="agent_code" class="mt-1 block w-full" name="agent_code"
        :default-value="detail?.agent_code" autocomplete="agent_code" placeholder="Agent Code" />
    </div>

    <!-- ── Shared personal information ───────────────────────────────────── -->
    <div class="grid gap-2 md:col-span-1">
      <Label for="first_name">First Name<span class="text-red-400">*</span></Label>
      <Input id="first_name" class="mt-1 block w-full" name="first_name"
        :default-value="detail?.first_name" autocomplete="first_name" placeholder="First Name" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="middle_name">Middle Name</Label>
      <Input id="middle_name" class="mt-1 block w-full" name="middle_name"
        :default-value="detail?.middle_name" autocomplete="middle_name" placeholder="Middle Name" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="last_name">Last Name<span class="text-red-400">*</span></Label>
      <Input id="last_name" class="mt-1 block w-full" name="last_name"
        :default-value="detail?.last_name" autocomplete="last_name" placeholder="Last Name" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="suffix">Suffix</Label>
      <Input id="suffix" class="mt-1 block w-full" name="suffix"
        :default-value="detail?.suffix" autocomplete="suffix" placeholder="Suffix" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="gender">Gender<span class="text-red-400">*</span></Label>
      <Select id="gender" class="mt-1 block w-full" name="gender_id"
        :default-value="detail?.gender_id ? String(detail?.gender_id) : undefined">
        <SelectTrigger class="w-full"><SelectValue placeholder="Select a gender" /></SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Gender</SelectLabel>
            <SelectItem v-for="gender in genders" :key="gender.value" :value="String(gender.value)">
              {{ gender.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="civil_status">Civil Status<span class="text-red-400">*</span></Label>
      <Select id="civil_status" class="mt-1 block w-full" name="civil_status_id"
        :default-value="detail?.civil_status_id ? String(detail?.civil_status_id) : undefined">
        <SelectTrigger class="w-full"><SelectValue placeholder="Select a civil status" /></SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Civil Status</SelectLabel>
            <SelectItem v-for="cs in civil_statuses" :key="cs.id" :value="String(cs.id)">
              {{ cs.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="citizenship">Citizenship<span class="text-red-400">*</span></Label>
      <Select id="citizenship" class="mt-1 block w-full" name="citizenship_id"
        :default-value="detail?.citizenship_id ? String(detail?.citizenship_id) : undefined">
        <SelectTrigger class="w-full"><SelectValue placeholder="Select a citizenship" /></SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Citizenship</SelectLabel>
            <SelectItem v-for="citizenship in citizenships" :key="citizenship.id" :value="String(citizenship.id)">
              {{ citizenship.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="birthdate">Birth Date</Label>
      <Input id="birthdate" type="date" class="mt-1 block w-full" name="birthdate"
        :default-value="detail?.birthdate" autocomplete="birthdate" placeholder="Birth Date" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="username">Username<span class="text-red-400">*</span></Label>
      <Input id="username" class="mt-1 block w-full" name="username"
        :default-value="user?.username" autocomplete="username" placeholder="Username" />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="email">Email<span class="text-red-400">*</span></Label>
      <Input id="email" class="mt-1 block w-full" name="email"
        :default-value="user?.email" autocomplete="email" placeholder="Email" />
    </div>

    <!-- ── Roles ─────────────────────────────────────────────────────────── -->
    <div v-if="all_roles.length" class="grid gap-2 md:col-span-2">
      <Label for="role-search">Roles</Label>
      <Input id="role-search" v-model="roleSearch" class="mt-1 block w-full"
        placeholder="Search by role name..." />
      <div class="border rounded-md max-h-56 overflow-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-muted sticky top-0 z-10">
            <tr>
              <th class="px-3 py-2 text-left w-10">#</th>
              <th class="px-3 py-2 text-left">Role</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in filteredRoles" :key="role.id" class="border-t">
              <td class="px-3 py-2">
                <Switch name="roles[]" :value="role.id" :default-value="isRoleChecked(role.id)" />
              </td>
              <td class="px-3 py-2">{{ role.name }}</td>
            </tr>
            <tr v-if="!filteredRoles.length">
              <td colspan="2" class="px-3 py-4 text-center text-muted-foreground">No roles found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</template>
