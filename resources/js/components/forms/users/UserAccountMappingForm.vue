<script setup lang="ts">
/**
 * Maps HMS accounts and branches onto a user by dragging them into the assigned panel.
 *
 * The available panel is one list in two modes: accounts for the chosen account type,
 * and — once an account is picked — that account's branches. Dropping an account
 * assigns every branch of it; dropping a branch narrows the mapping to that branch,
 * which is the same distinction `branch_code` carries in `user_accounts`.
 *
 * Both lists are searched and paged server-side (`users.get_accounts` /
 * `users.get_branches`), so a directory of thousands is never pulled down to be
 * filtered in the browser. Saving posts the whole assigned set, since that endpoint
 * treats the payload as the complete intended state — it grants and revokes at once.
 */
import { computed, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import DragDropTransfer from '@/components/DragDropTransfer.vue';
import { useUsers, type UserAccountMapping } from '@/composables/users';
import { debounce } from '@/composables/utilities/helper';
import { ArrowLeft, Building2, ChevronRight, Info, RotateCcw, Save, Search, X } from 'lucide-vue-next';

type Option = { value: string | number; name: string };

/** One row of the available panel, whichever directory it came from. */
type SourceItem = {
  key: string;
  kind: 'account' | 'branch';
  account_type: string;
  account_code: string;
  account_name: string;
  branch_code: string;
  branch_name: string;
  title: string;
  subtitle: string;
};

const props = withDefaults(
  defineProps<{
    userId: number | string;
    /** Mappings already saved for this user. */
    mappings?: UserAccountMapping[];
    accountTypes?: Option[];
    /** False when the user's type is not one that mappings apply to. */
    allowsMapping?: boolean;
    /** Cap from the user's type; null when unlimited. */
    limit?: number | null;
    typeLabel?: string | null;
  }>(),
  {
    mappings: () => [],
    accountTypes: () => [],
    allowsMapping: true,
    limit: null,
    typeLabel: null,
  },
);

const emit = defineEmits<{
  saved: [mappings: UserAccountMapping[]];
}>();

const { getAccountsByParams, getBranchesByParams, saveUserAccountMapping } = useUsers();

/**
 * Both ends must agree on what makes two mappings the same pair, so this mirrors
 * `UserAccount::mappingKey()` exactly — the account and branch codes and nothing else.
 * Account type is deliberately not part of it: it does not affect what a mapping
 * grants, so changing the type filter must not offer an already-mapped pair again.
 */
const mappingKey = (accountCode: unknown, branchCode: unknown): string =>
  [accountCode, branchCode].map((part) => String(part ?? '').trim()).join('|');

/** Account options already read "Name (CODE)"; keep the bare name so every row labels alike. */
const bareAccountName = (label: string, code: string): string => {
  const suffix = ` (${code})`;

  return label.endsWith(suffix) ? label.slice(0, -suffix.length) : label;
};

const toMapping = (row: Partial<UserAccountMapping>): UserAccountMapping => {
  const accountType = String(row.account_type ?? '');
  const accountCode = String(row.account_code ?? '');
  const branchCode = String(row.branch_code ?? '');

  return {
    id: row.id ?? null,
    key: mappingKey(accountCode, branchCode),
    account_type: accountType,
    account_type_label: row.account_type_label ?? null,
    account_code: accountCode,
    account_name: String(row.account_name ?? '') || accountCode,
    branch_code: branchCode,
    branch_name: String(row.branch_name ?? '') || branchCode,
  };
};

// ─── Assigned set ─────────────────────────────────────────────────────────
const assigned = ref<UserAccountMapping[]>([]);
/** What the server last confirmed, so "unsaved changes" is a real comparison. */
const savedKeys = ref<string[]>([]);

const syncFromProps = () => {
  assigned.value = props.mappings.map(toMapping);
  savedKeys.value = assigned.value.map((mapping) => mapping.key);
};

watch(() => props.mappings, syncFromProps, { immediate: true, deep: true });

const isDirty = computed(() => {
  const current = assigned.value.map((mapping) => mapping.key);

  return current.length !== savedKeys.value.length
    || current.some((key, index) => key !== savedKeys.value[index]);
});

// ─── Available panel ──────────────────────────────────────────────────────
const DEFAULT_ACCOUNT_TYPE = 'A'; // AccountType::TPA_HMO — spans both directories

const accountType = ref<string>(DEFAULT_ACCOUNT_TYPE);
/** Which directory the available panel is showing. */
const mode = ref<'accounts' | 'branches'>('accounts');
/** Account whose branches are listed, kept so branch rows know what they belong to. */
const focusedAccount = ref<{ code: string; name: string } | null>(null);

const accounts = ref<Option[]>([]);
const branches = ref<Option[]>([]);
const search = ref('');
const loading = ref(false);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);

const hasMore = computed(() => page.value < lastPage.value);
const isBranchMode = computed(() => mode.value === 'branches' && focusedAccount.value !== null);

/**
 * The available rows, normalised so the transfer list — and a drop — treats an account
 * and a branch identically.
 */
const sourceItems = computed<SourceItem[]>(() => {
  const type = accountType.value;

  if (isBranchMode.value) {
    const account = focusedAccount.value!;

    return branches.value.map((branch) => {
      const branchCode = String(branch.value ?? '');

      return {
        key: mappingKey(account.code, branchCode),
        kind: 'branch' as const,
        account_type: type,
        account_code: account.code,
        account_name: account.name,
        branch_code: branchCode,
        branch_name: branch.name,
        title: branch.name || branchCode,
        subtitle: `${account.name} · branch ${branchCode}`,
      };
    });
  }

  return accounts.value.map((account) => {
    const accountCode = String(account.value ?? '');
    const accountName = bareAccountName(account.name, accountCode);

    return {
      key: mappingKey(accountCode, ''),
      kind: 'account' as const,
      account_type: type,
      account_code: accountCode,
      account_name: accountName,
      branch_code: '',
      branch_name: '',
      title: accountName || accountCode,
      subtitle: `${accountCode} · all branches`,
    };
  });
});

const fetchAccounts = async (name = '', nextPage = 1, append = false) => {
  if (append) loadingMore.value = true;
  else loading.value = true;

  const result = await getAccountsByParams({ type: accountType.value, name, page: nextPage });

  accounts.value = append ? [...accounts.value, ...(result?.data ?? [])] : (result?.data ?? []);
  page.value = result?.current_page ?? 1;
  lastPage.value = result?.last_page ?? 1;
  loading.value = false;
  loadingMore.value = false;
};

const fetchBranches = async (name = '', nextPage = 1, append = false) => {
  const account = focusedAccount.value;
  if (!account) return;

  if (append) loadingMore.value = true;
  else loading.value = true;

  const result = await getBranchesByParams({ account_code: account.code, name, page: nextPage });

  branches.value = append ? [...branches.value, ...(result?.data ?? [])] : (result?.data ?? []);
  page.value = result?.current_page ?? 1;
  lastPage.value = result?.last_page ?? 1;
  loading.value = false;
  loadingMore.value = false;
};

/** One entry point, so search, paging and mode changes all refill the same list. */
const refresh = (name = search.value, nextPage = 1, append = false) =>
  isBranchMode.value ? fetchBranches(name, nextPage, append) : fetchAccounts(name, nextPage, append);

const debouncedSearch = debounce(() => void refresh(search.value, 1, false), 400);

const loadMore = () => {
  if (!hasMore.value || loadingMore.value) return;
  void refresh(search.value, page.value + 1, true);
};

/** Narrow the panel to one account's branches. */
const focusAccount = (item: SourceItem) => {
  focusedAccount.value = { code: item.account_code, name: item.account_name };
  mode.value = 'branches';
  search.value = '';
  branches.value = [];
  void fetchBranches('', 1, false);
};

const backToAccounts = () => {
  mode.value = 'accounts';
  focusedAccount.value = null;
  search.value = '';
  void fetchAccounts('', 1, false);
};

watch(accountType, () => {
  mode.value = 'accounts';
  focusedAccount.value = null;
  search.value = '';
  void fetchAccounts('', 1, false);
});

onMounted(() => void fetchAccounts());

// ─── Transfers ────────────────────────────────────────────────────────────
const assign = (item: SourceItem) => {
  if (assigned.value.some((mapping) => mapping.key === item.key)) return;

  assigned.value = [
    ...assigned.value,
    toMapping({
      account_type: item.account_type,
      account_code: item.account_code,
      account_name: item.account_name,
      branch_code: item.branch_code,
      branch_name: item.branch_name,
    }),
  ];
};

const unassign = (_item: UserAccountMapping, index: number) => {
  assigned.value = assigned.value.filter((_, position) => position !== index);
};

const reorder = (next: UserAccountMapping[]) => {
  assigned.value = next;
};

const clearAll = () => {
  assigned.value = [];
};

const reset = () => syncFromProps();

// ─── Save ─────────────────────────────────────────────────────────────────
const saving = ref(false);

const save = async () => {
  saving.value = true;

  try {
    const result = await saveUserAccountMapping(props.userId, assigned.value);

    if (!result?.ok) return;

    // Re-seed from the server so labels and ids match what was actually stored.
    assigned.value = (result.user_accounts ?? []).map(toMapping);
    savedKeys.value = assigned.value.map((mapping) => mapping.key);
    emit('saved', assigned.value);
  } finally {
    saving.value = false;
  }
};

const limitReached = computed(
  () => props.limit !== null && assigned.value.length >= (props.limit ?? 0),
);

const accountTypeName = computed(
  () => props.accountTypes.find((option) => String(option.value) === accountType.value)?.name ?? '',
);
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- Why the panels are inert, when they are -->
    <div
      v-if="!allowsMapping"
      class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-50/40 px-3 py-2 text-xs text-amber-700 dark:bg-amber-900/10 dark:text-amber-300">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>
        <strong>{{ typeLabel || 'This user type' }}</strong> accounts are not scoped by
        account and branch mappings, so anything assigned here would have no effect.
        Change the user's type first if they need account-scoped access.
      </span>
    </div>
    <div
      v-else-if="limit !== null"
      class="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-3 py-2 text-xs text-[var(--color-text-muted)]">
      <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
      <span>
        <strong>{{ typeLabel || 'This user type' }}</strong> is scoped to
        {{ limit === 1 ? 'a single account / branch pair' : `${limit} account / branch pairs` }}.
        Remove the current mapping to assign a different one.
      </span>
    </div>

    <DragDropTransfer
      :source="sourceItems"
      :target="assigned"
      item-key="key"
      :source-title="isBranchMode ? 'Branches' : 'Accounts'"
      target-title="Mapped Accounts & Branches"
      :source-hint="isBranchMode
        ? 'Assign one branch of this account'
        : 'Assign an account to cover all of its branches'"
      :source-empty="isBranchMode ? 'No branches match this search.' : 'No accounts match this search.'"
      target-empty="No accounts or branches mapped yet."
      :source-loading="loading"
      :disabled="!allowsMapping"
      :max="limit"
      reorderable
      list-class="max-h-80"
      @add="assign"
      @remove="unassign"
      @reorder="reorder">
      <!-- Directory controls: account type, breadcrumb back to accounts, and search -->
      <template #source-toolbar>
        <div class="mt-2 flex flex-col gap-2">
          <div v-if="isBranchMode" class="flex items-center gap-1 text-xs">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="h-6 shrink-0 px-1.5 text-xs"
              @click="backToAccounts">
              <ArrowLeft class="mr-1 h-3 w-3" /> Accounts
            </Button>
            <ChevronRight class="h-3 w-3 shrink-0 text-[var(--color-text-muted)]" aria-hidden="true" />
            <span class="truncate font-medium" :title="focusedAccount?.name">
              {{ focusedAccount?.name }}
            </span>
          </div>

          <div v-else class="flex items-center gap-2">
            <Label class="shrink-0 text-xs text-[var(--color-text-muted)]" for="mapping_account_type">
              Type
            </Label>
            <Select id="mapping_account_type" v-model="accountType">
              <SelectTrigger class="h-7 flex-1 text-xs">
                <SelectValue :placeholder="accountTypeName || 'Account type'" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="option in accountTypes"
                    :key="String(option.value)"
                    :value="String(option.value)"
                    class="text-xs">
                    {{ option.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <div class="relative">
            <Search
              class="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
              aria-hidden="true" />
            <input
              v-model="search"
              type="text"
              :placeholder="isBranchMode ? 'Search branches...' : 'Search accounts...'"
              class="w-full rounded-md border border-[var(--color-border-strong)] bg-[var(--color-surface)] py-1.5 pr-7 pl-7 text-xs text-[var(--color-text)] focus:border-transparent focus:ring-2 focus:ring-opacity-50"
              :style="{ '--tw-ring-color': 'var(--primary-color)' }"
              :aria-label="isBranchMode ? 'Search branches' : 'Search accounts'"
              @input="debouncedSearch" />
            <button
              v-if="search"
              type="button"
              class="absolute top-1/2 right-2 -translate-y-1/2 text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
              aria-label="Clear search"
              @click="search = ''; debouncedSearch()">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </template>

      <template #source-item="{ item, assigned: isMapped }">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="truncate font-medium" :title="item.title">{{ item.title }}</p>
            <p class="truncate text-xs text-[var(--color-text-muted)]" :title="item.subtitle">
              {{ item.subtitle }}
            </p>
          </div>
          <!-- Drill into an account's branches without assigning the account itself -->
          <Button
            v-if="item.kind === 'account'"
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 shrink-0 px-1.5 text-xs text-[var(--color-text-muted)]"
            :title="`Show branches of ${item.title}`"
            @click.stop="focusAccount(item)">
            <Building2 class="h-3.5 w-3.5" />
          </Button>
        </div>
        <p v-if="isMapped" class="mt-0.5 text-xs text-green-600">Already mapped</p>
      </template>

      <template #source-footer>
        <div v-if="hasMore" class="pt-2 text-center">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-7 text-xs"
            :disabled="loadingMore"
            @click="loadMore">
            {{ loadingMore ? 'Loading...' : 'Load more' }}
          </Button>
        </div>
      </template>

      <template #target-toolbar>
        <div v-if="assigned.length" class="mt-2 flex items-center justify-between gap-2">
          <span v-if="limitReached" class="text-xs text-amber-600 dark:text-amber-400">
            Limit reached
          </span>
          <span v-else class="text-xs text-[var(--color-text-muted)]">
            Drag to reorder
          </span>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="h-6 px-1.5 text-xs text-red-500 hover:text-red-700"
            :disabled="!allowsMapping"
            @click="clearAll">
            Clear all
          </Button>
        </div>
      </template>

      <template #target-item="{ item }">
        <div class="min-w-0">
          <p class="truncate font-medium" :title="item.account_name">
            {{ item.account_name }}
            <span class="text-xs font-normal text-[var(--color-text-muted)]">
              ({{ item.account_code }})
            </span>
          </p>
          <p class="truncate text-xs text-[var(--color-text-muted)]">
            <template v-if="item.branch_code">
              {{ item.branch_name }} · branch {{ item.branch_code }}
            </template>
            <template v-else>All branches</template>
            <template v-if="item.account_type_label"> · {{ item.account_type_label }}</template>
          </p>
        </div>
      </template>
    </DragDropTransfer>

    <!-- Save bar -->
    <div class="flex items-center justify-between gap-2 border-t border-[var(--color-border)] pt-3">
      <p class="text-xs text-[var(--color-text-muted)]">
        <template v-if="isDirty">Unsaved changes</template>
        <template v-else>
          {{ assigned.length }}
          {{ assigned.length === 1 ? 'mapping' : 'mappings' }} saved
        </template>
      </p>
      <div class="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="cursor-pointer"
          :disabled="!isDirty || saving"
          @click="reset">
          <RotateCcw class="mr-1 h-3.5 w-3.5" /> Reset
        </Button>
        <Button
          type="button"
          size="sm"
          class="cursor-pointer"
          :disabled="!isDirty || saving || !allowsMapping"
          @click="save">
          <Save class="mr-1 h-3.5 w-3.5" />
          {{ saving ? 'Saving...' : 'Save Mapping' }}
        </Button>
      </div>
    </div>
  </div>
</template>
