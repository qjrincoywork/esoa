<script setup lang="ts">
import { ref, onMounted, computed, defineExpose, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableCombobox } from '@/components/ui/searchable-combobox';
import { useUsers } from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';
import { Switch } from '@/components/ui/switch';
import { Select, SelectTrigger, SelectContent, SelectGroup, SelectLabel, SelectItem, SelectValue } from '@/components/ui/select';

type Type = { value: string | number; name: string }
type AccountType = { value: string | number; name: string }
type Account = { value: string | number; name: string }
type Branch = { value: string | number; name: string }
type Gender = { value: string | number; name: string }
type CivilStatus = { id: string | number; name: string }
type Citizenship = { id: string | number; name: string }
type Department = { id: string | number; name: string }
type Position = { id: string | number; name: string }
type UserDetail = {
  account_type?: string
  type?: number
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
  onReady: {
    type: Function as unknown as () => ((api: { getFormData: () => FormData | null; formRef: HTMLFormElement | null }) => void) | undefined,
    required: false,
  },
});

// Prefer nested user_detail if present; fallback to user for backward compatibility
const user = computed<UserBasic>(() => props.user as UserBasic)
const detail = computed<UserDetail>(() => user.value?.user_detail as UserDetail)
const genders = computed<Gender[]>(() => props.genders as Gender[]);
const civil_statuses = computed<CivilStatus[]>(() => props.civil_statuses as CivilStatus[]);
const citizenships = computed<Citizenship[]>(() => props.citizenships as Citizenship[]);
const departments = computed<Department[]>(() => props.departments as Department[]);
const positions = computed<Position[]>(() => props.positions as Position[]);
// const selectedAccountType = ref<string | null>(null)
const selectedAccountType = ref(detail.value?.account_type != null ? String(detail.value.account_type) : 1)
const account_types = computed<AccountType[]>(() => props.account_types as AccountType[]);
const types = computed<Type[]>(() => props.types as Type[]);
const accounts = ref<Account[]>([])
const branches = ref<Branch[]>([])
const accountPage = ref(1)
const accountLastPage = ref(1)
const branchPage = ref(1)
const branchLastPage = ref(1)
const accountsLoadingMore = ref(false)
const branchesLoadingMore = ref(false)
const hasMoreAccounts = computed(() => accountPage.value < accountLastPage.value)
const hasMoreBranches = computed(() => branchPage.value < branchLastPage.value)
const { getAccountsByParams, getBranchesByParams } = useUsers();
const userType = ref(detail.value?.type != null ? Number(detail.value.type) : 1)
const userId = ref(user?.value?.id ?? undefined)

// Sync from props when user/detail loads (e.g. edit mode with async data)
watch(
  () => detail.value?.type,
  (val) => {
    if (val != null) {
      userType.value = Number(val)
    }
  },
  { immediate: true },
)

const isShowFields = computed(() => userType.value == 1 ? 1 :0)
const accountCode = ref(detail.value?.account_code != null ? String(detail.value.account_code) : '')
const branchCode = ref(detail.value?.branch_code != null ? String(detail.value.branch_code) : '')
const searchedAccountName = ref('')
const searchedBranchName = ref('')
const selectedAccount = computed(() =>
  accounts.value?.find(account => String(account.value) === accountCode.value),
)
// selectedAccount still needed for Branch combobox disabled state and branch fetch

// Expose a form ref
const userEditForm = ref<HTMLFormElement | null>(null)

// Helper to extract FormData from this form (exposed to parent)
function getFormData(): FormData | null {
  if (!userEditForm.value) return null
  return new FormData(userEditForm.value)
}

defineExpose({
  userEditForm,
  getFormData,
})
onMounted(() => {
  if (typeof props.onReady === 'function') {
    props.onReady({ getFormData, formRef: userEditForm.value })
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

  if (append) {
    branches.value = [...(branches.value ?? []), ...result?.data];
  } else {
    branches.value = result?.data;
  }
  branchPage.value = result?.current_page;
  branchLastPage.value = result?.last_page;
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
watch([selectedAccount, searchedBranchName], async () => {
  if (selectedAccount.value?.value != null) {
    if (searchedBranchName.value.length > 0) {
      debouncedGetBranches(searchedBranchName.value);
    } else {
      await searchBranchesByParams();
    }
  }
}, { immediate: true })
</script>

<template>
  <form ref="userEditForm" class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div class="md:col-span-2 hidden">
        <Input
          id="id"
          type="hidden"
          class="mt-1 block w-full"
          name="id"
          v-model="userId"
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
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="type">User Type<span class="text-red-400">*</span></Label>
      <Select
        id="type"
        class="mt-1 block w-full"
        name="type"
        :default-value="userType"
        v-model="userType"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select User type" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>User Type</SelectLabel>
            <SelectItem
              v-for="type in types"
              :key="type.value"
              :value="Number(type.value)"
            >
            {{ type.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="!isShowFields" class="grid gap-2 md:col-span-1">
      <Label for="account_type">Account Type<span class="text-red-400">*</span></Label>
      <Select
        id="account_type"
        class="mt-1 block w-full"
        name="account_type"
        v-model="selectedAccountType"
      >
        <!-- :default-value="detail?.account_type ? String(detail?.account_type) : undefined" -->
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

    <div v-if="!isShowFields" class="md:col-span-1">
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

    <div v-if="!isShowFields" class="md:col-span-1">
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

    <div v-if="isShowFields" class="grid gap-2 md:col-span-1">
      <Label for="department">Department<span class="text-red-400">*</span></Label>
      <Select
          id="department"
          class="mt-1 block w-full"
          name="department_id"
          :default-value="detail?.department_id ? Number(detail?.department_id) : undefined"
      >
        <SelectTrigger class="w-full">
            <SelectValue placeholder="Select a department" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Department</SelectLabel>
            <SelectItem
              v-for="department in departments"
              :key="department.id"
              :value="Number(department.id)"
            >
            {{ department.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="isShowFields" class="grid gap-2 md:col-span-1">
      <Label for="position">Position<span class="text-red-400">*</span></Label>
      <Select
        id="position"
        class="mt-1 block w-full"
        name="position_id"
        :default-value="detail?.position_id ? String(detail?.position_id) : undefined"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select a position" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Position</SelectLabel>
            <SelectItem
              v-for="position in positions"
              :key="position.id"
              :value="String(position.id)"
            >
            {{ position.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div v-if="isShowFields" class="grid gap-2 md:col-span-1">
      <Label for="employee_no">Employee No<span class="text-red-400">*</span></Label>
      <Input
        id="employee_no"
        class="mt-1 block w-full"
        name="employee_no"
        :default-value="detail?.employee_no"
        autocomplete="employee_no"
        placeholder="Employee No"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="first_name">First Name<span class="text-red-400">*</span></Label>
      <Input
        id="first_name"
        class="mt-1 block w-full"
        name="first_name"
        :default-value="detail?.first_name"
        autocomplete="first_name"
        placeholder="First Name"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="middle_name">Middle Name</Label>
      <Input
        id="middle_name"
        class="mt-1 block w-full"
        name="middle_name"
        :default-value="detail?.middle_name"
        autocomplete="middle_name"
        placeholder="Middle Name"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="last_name">Last Name<span class="text-red-400">*</span></Label>
      <Input
        id="last_name"
        class="mt-1 block w-full"
        name="last_name"
        :default-value="detail?.last_name"
        autocomplete="last_name"
        placeholder="Last Name"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="suffix">Suffix</Label>
      <Input
        id="suffix"
        class="mt-1 block w-full"
        name="suffix"
        :default-value="detail?.suffix"
        autocomplete="suffix"
        placeholder="Suffix"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="gender">Gender<span class="text-red-400">*</span></Label>
      <Select
        id="gender"
        class="mt-1 block w-full"
        name="gender_id"
        :default-value="detail?.gender_id ? String(detail?.gender_id) : undefined"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select a gender" />
        </SelectTrigger>
        <SelectContent class="w-full">
          <SelectGroup>
            <SelectLabel>Gender</SelectLabel>
            <SelectItem
              v-for="gender in genders"
              :key="gender.value"
              :value="String(gender.value)"
            >
            {{ gender.name }}
            </SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="civil_status">Civil Status<span class="text-red-400">*</span></Label>
      <Select
        id="civil_status"
        class="mt-1 block w-full"
        name="civil_status_id"
        :default-value="detail?.civil_status_id ? String(detail?.civil_status_id) : undefined"
      >
      <SelectTrigger class="w-full">
        <SelectValue placeholder="Select a civil_status" />
      </SelectTrigger>
      <SelectContent class="w-full">
        <SelectGroup>
          <SelectLabel>Civil Status</SelectLabel>
          <SelectItem
            v-for="civil_status in civil_statuses"
            :key="civil_status.id"
            :value="String(civil_status.id)"
          >
          {{ civil_status.name }}
          </SelectItem>
        </SelectGroup>
      </SelectContent>
    </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="citizenship">Citizenship<span class="text-red-400">*</span></Label>
      <Select
        id="citizenship"
        class="mt-1 block w-full"
        name="citizenship_id"
        :default-value="detail?.citizenship_id ? String(detail?.citizenship_id) : undefined"
      >
      <SelectTrigger class="w-full">
        <SelectValue placeholder="Select a citizenship" />
      </SelectTrigger>
      <SelectContent class="w-full">
        <SelectGroup>
          <SelectLabel>Citizenship</SelectLabel>
          <SelectItem
            v-for="citizenship in citizenships"
            :key="citizenship.id"
            :value="String(citizenship.id)"
          >
          {{ citizenship.name }}
          </SelectItem>
        </SelectGroup>
      </SelectContent>
      </Select>
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="birthdate">Birth Date</Label>
      <Input
        id="birthdate"
        type="date"
        class="mt-1 block w-full"
        name="birthdate"
        :default-value="detail?.birthdate"
        autocomplete="birthdate"
        placeholder="Birth Date"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="username">Username<span class="text-red-400">*</span></Label>
      <Input
        id="username"
        class="mt-1 block w-full"
        name="username"
        :default-value="user?.username"
        autocomplete="username"
        placeholder="Username"
      />
    </div>

    <div class="grid gap-2 md:col-span-1">
      <Label for="email">Email<span class="text-red-400">*</span></Label>
      <Input
        id="email"
        class="mt-1 block w-full"
        name="email"
        :default-value="user?.email"
        autocomplete="email"
        placeholder="Email"
      />
    </div>
  </form>
</template>
